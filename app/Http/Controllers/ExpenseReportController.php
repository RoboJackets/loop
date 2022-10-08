<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Http\Controllers;

use App\Http\Requests\UpdateExpenseReport;
use App\Http\Requests\UpsertExpenseReports;
use App\Models\ExpensePayment;
use App\Models\ExpenseReport;
use App\Models\ExpenseReportLine;
use App\Models\ExternalCommitteeMember;
use App\Models\FiscalYear;
use App\Models\User;
use App\Util\Workday;
use Illuminate\Http\JsonResponse;

class ExpenseReportController extends Controller
{
    private const LABEL_TO_COLUMN_NAME = [
        'Expense Report Memo' => 'memo',
        'Created Date' => 'created_date',
        'Total Expense Report Amount' => 'amount',
    ];

    private const VALID_EXPENSE_REPORT_STATUSES = [
        'Paid',
        'Canceled',
        'Approved',
        'Waiting on Expense Partner',
    ];

    private const VALID_PAYMENT_STATUSES = [
        'Complete',
    ];

    private const RECONCILIATION_STATUSES = [
        'Reconciled' => true,
        'Unreconciled' => false,
    ];

    private const WORKDAY_NAME_REGEX = '/^Expense Report: (?P<expense_report_number>EXP-\d+)( Canceled)?$/';

    /**
     * Accept Workday report output and create expense reports based on the data.
     *
     * @phan-suppress PhanTypeMismatchForeach
     * @phan-suppress PhanTypePossiblyInvalidDimOffset
     */
    public function store(UpsertExpenseReports $request): JsonResponse
    {
        $sync_expense_reports = [];
        $sync_workers = [];
        $sync_external_committee_members = [];

        foreach ($request['rows'] as $row) {
            $attributes = [];
            foreach ($row['cellsMap'] as $cell) {
                if ($cell['label'] === 'Budget Reference') {
                    $attributes['fiscal_year_id'] = FiscalYear::whereEndingYear(
                        '20'.substr($cell['instances'][0]['text'], 2)
                    )->sole()->id;
                } elseif ($cell['label'] === 'Expense Report') {
                    $text = $cell['instances'][0]['text'];
                    $matches = [];
                    if (preg_match(self::WORKDAY_NAME_REGEX, $text, $matches) !== 1) {
                        return response()->json(
                            [
                                'error' => 'Failed to parse expense report number \''.$text.'\'',
                            ],
                            422
                        );
                    }

                    $attributes['workday_expense_report_id'] = $matches['expense_report_number'];
                    $attributes['workday_instance_id'] = Workday::getInstanceId($cell);
                } elseif ($cell['label'] === 'Pay to') {
                    $name = $cell['instances'][0]['text'];
                    $instance_id = Workday::getInstanceId($cell);
                    $attributes['external_committee_member_id'] = $instance_id;
                    if (! in_array($instance_id, $sync_external_committee_members, true)) {
                        if (ExternalCommitteeMember::whereWorkdayInstanceId($instance_id)->exists()) {
                            if (
                                ExternalCommitteeMember::whereWorkdayInstanceId($instance_id)
                                    ->sole()
                                    ->active === str_ends_with($name, ' - Inactive')
                            ) {
                                $sync_external_committee_members[] = $instance_id;
                            }
                        } else {
                            $sync_external_committee_members[] = $instance_id;
                        }
                    }
                } elseif ($cell['label'] === 'Expense Report Created By') {
                    $name = $cell['instances'][0]['text'];
                    $instance_id = Workday::getInstanceId($cell);
                    $attributes['created_by_worker_id'] = $instance_id;
                    if (! in_array($instance_id, $sync_workers, true)) {
                        if (User::whereWorkdayInstanceId($instance_id)->exists()) {
                            if (
                                User::whereWorkdayInstanceId($instance_id)
                                    ->sole()
                                    ->active_employee === str_ends_with($name, ' (Terminated)')
                            ) {
                                $sync_workers[] = $instance_id;
                            }
                        } else {
                            $sync_workers[] = $instance_id;
                        }
                    }
                } elseif (array_key_exists($cell['label'], self::LABEL_TO_COLUMN_NAME)) {
                    if ($cell['widget'] === 'text' || $cell['widget'] === 'currency') {
                        $attributes[self::LABEL_TO_COLUMN_NAME[$cell['label']]] = $cell['value'];
                    } elseif ($cell['widget'] === 'date') {
                        $attributes[self::LABEL_TO_COLUMN_NAME[$cell['label']]] = Workday::getDate($cell);
                    }
                }
            }

            $expense_report = ExpenseReport::updateOrCreate(
                [
                    'workday_expense_report_id' => $attributes['workday_expense_report_id'],
                    'workday_instance_id' => $attributes['workday_instance_id'],
                ],
                $attributes
            );

            if (! in_array($attributes['workday_instance_id'], $sync_expense_reports, true)) {
                if ($expense_report->status === null) {
                    $sync_expense_reports[] = $attributes['workday_instance_id'];
                } elseif ($expense_report->status !== 'Canceled' && $expense_report->expense_payment_id === null) {
                    $sync_expense_reports[] = $attributes['workday_instance_id'];
                }
            }
        }

        return response()->json(
            [
                'expense-reports' => $sync_expense_reports,
                'workers' => $sync_workers,
                'external-committee-members' => $sync_external_committee_members,
            ]
        );
    }

    /**
     * Accept Workday expense report output and update the internal representation.
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanTypeMismatchDimFetch
     */
    public function update(ExpenseReport $expense_report, UpdateExpenseReport $request): JsonResponse
    {
        $header_widgets = $request['body']['children'][0];

        $expense_report_attributes = [
            'status' => Workday::sole($header_widgets, 'wd:Expense_Report_Status')['value'],
            'external_committee_member_id' => Workday::getInstanceId(
                Workday::sole($header_widgets, 'wd:Expense_Payee_for_Expense_Documents--IS')
            ),
            'amount' => Workday::sole($header_widgets, 'wd:Total_Reimbursement_Amount')['value'],
            'memo' => Workday::sole($header_widgets, 'wd:Document_Memo')['value'],
        ];

        $approval_date = Workday::sole($header_widgets, 'wd:Approval_Date');

        $expense_report_attributes['approval_date'] = array_key_exists('value', $approval_date)
            ? Workday::getDate($approval_date)
            : null;

        if (! in_array($expense_report_attributes['status'], self::VALID_EXPENSE_REPORT_STATUSES, true)) {
            return response()->json(
                [
                    'error' => 'Unrecognized expense report status \''.$expense_report_attributes['status'].'\'',
                ],
                422
            );
        }

        $expense_payment_widgets = $request['body']['children'][3];

        $widgets = Workday::searchForKeyValuePair($expense_payment_widgets, 'propertyName', 'wd:Expense_Payment--IS');

        if (count($widgets) === 1) {
            $expense_report_attributes['expense_payment_id'] = Workday::getInstanceId($widgets[0]);

            $expense_payment_attributes = [
                'workday_instance_id' => Workday::getInstanceId($widgets[0]),
                'status' => Workday::sole($expense_payment_widgets, 'wd:Payment_Status_as_Text')['value'],
                'reconciled' => self::RECONCILIATION_STATUSES[Workday::sole(
                    $expense_payment_widgets,
                    'wd:Reconciled_Status_for_Reconcilable_Item__Persisted_--IS'
                )['instances'][0]['text']],
                'external_committee_member_id' => Workday::getInstanceId(
                    Workday::sole($expense_payment_widgets, 'wd:Expense_Payment.pays_Expense_Payee--IS')
                ),
                'payment_date' => Workday::getDate(Workday::sole($expense_payment_widgets, 'wd:Payment_Date')),
                'amount' => Workday::sole($expense_payment_widgets, 'wd:Payment_Amount')['value'],
                'transaction_reference' => Workday::sole($expense_payment_widgets, 'wd:Reference_Number')['value'],
            ];

            if (! in_array($expense_payment_attributes['status'], self::VALID_PAYMENT_STATUSES, true)) {
                return response()->json(
                    [
                        'error' => 'Unrecognized expense payment status \''.$expense_payment_attributes['status']
                            .'\'',
                    ],
                    422
                );
            }

            ExpensePayment::updateOrCreate(
                [
                    'workday_instance_id' => $expense_payment_attributes['workday_instance_id'],
                ],
                $expense_payment_attributes
            );

            $expense_payment_table = Workday::sole($expense_payment_widgets, 'wd:Expense_Report_SubView');

            foreach ($expense_payment_table['rows'] as $row) {
                foreach ($row['cellsMap'] as $cell) {
                    if ($cell['propertyName'] === 'wd:Expense_Report--IS') {
                        $instance_id = Workday::getInstanceId($cell);
                        if (ExpenseReport::whereWorkdayInstanceId($instance_id)->exists()) {
                            $inner_expense_report = ExpenseReport::whereWorkdayInstanceId($instance_id)->sole();
                            $inner_expense_report->expense_payment_id =
                                $expense_payment_attributes['workday_instance_id'];
                            $inner_expense_report->save();
                        }
                    }
                }
            }
        }

        $lines_table = collect(
            Workday::searchForKeyValuePair($request['body']['children'], 'label', 'Expense Lines')
        )->sole(static fn (array $input): bool => $input['widget'] === 'grid');

        foreach ($lines_table['rows'] as $row) {
            $key_attributes = [
                'workday_line_id' => $row['id'],
                'expense_report_id' => $expense_report['workday_instance_id'],
            ];
            $update_attributes = [];

            foreach ($row['cellsMap'] as $cell) {
                if ($cell['propertyName'] === 'wd:Charge_Description_Memo') {
                    $update_attributes['memo'] = $cell['value'];
                } elseif ($cell['propertyName'] === 'wd:Converted_Amount') {
                    $update_attributes['amount'] = $cell['value'];
                }
            }

            ExpenseReportLine::updateOrCreate($key_attributes, $update_attributes);
        }

        $expense_report->fill($expense_report_attributes);
        $expense_report->save();

        return response()->json($expense_report);
    }
}
