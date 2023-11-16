<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed
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
use Illuminate\Support\ItemNotFoundException;

class ExpenseReportController extends Controller
{
    private const LABEL_TO_COLUMN_NAME = [
        'Expense Report Memo' => 'memo',
        'Created Date' => 'created_date',
        'Total Expense Report Amount' => 'amount',
    ];

    private const VALID_EXPENSE_REPORT_STATUSES = [
        'In Progress',
        'Waiting on Cost Center Manager',
        'Waiting on Gift Manager',
        'Waiting on Expense Partner',
        'Approved',
        'Paid',
        'Canceled',
        'Draft',
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
                        if (array_key_exists('value', $cell)) {
                            $attributes[self::LABEL_TO_COLUMN_NAME[$cell['label']]] = $cell['value'];
                        } else {
                            $attributes[self::LABEL_TO_COLUMN_NAME[$cell['label']]] = null;
                        }
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
                } elseif (
                    $expense_report->expense_payment_id !== null &&
                    ExpensePayment::whereId($expense_report->expense_payment_id)->exists() &&
                    ! ExpensePayment::whereId($expense_report->expense_payment_id)->sole()->reconciled
                ) {
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
        $c = 1;

        try {
            Workday::sole($request['body']['children'][1], 'wd:Document_Memo');
        } catch (ItemNotFoundException) {
            $c = 2;
        }

        $expense_report_attributes = [
            'status' => Workday::sole($request['body']['children'][0], 'wd:Expense_Report_Status')['value'],
            'external_committee_member_id' => Workday::getInstanceId(
                Workday::sole($request['body']['children'][0], 'wd:Expense_Payee_for_Expense_Documents--IS')
            ),
            'amount' => Workday::sole($request['body']['children'][0], 'wd:Total_Reimbursement_Amount')['value'],
            'memo' => Workday::sole($request['body']['children'][$c], 'wd:Document_Memo')['value'],
        ];

        $approval_date = Workday::sole($request['body']['children'][$c], 'wd:Approval_Date');

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

            ExpensePayment::upsert(
                [
                    $expense_payment_attributes,
                ],
                [
                    'workday_instance_id',
                ],
                [
                    'status',
                    'reconciled',
                ]
            );

            $expense_payment_table = Workday::sole($expense_payment_widgets, 'wd:Expense_Report_SubView');

            $expense_reports_to_upsert = [];

            foreach ($expense_payment_table['rows'] as $row) {
                foreach ($row['cellsMap'] as $cell) {
                    if ($cell['propertyName'] === 'wd:Expense_Report--IS') {
                        $expense_reports_to_upsert[] = Workday::getInstanceId($cell);
                    }
                }
            }

            ExpenseReport::whereIn('workday_instance_id', $expense_reports_to_upsert)
                ->update(['expense_payment_id' => $expense_payment_attributes['workday_instance_id']]);
        }

        $lines_table = collect(
            Workday::searchForKeyValuePair($request['body']['children'], 'label', 'Expense Lines')
        )->sole(static fn (array $input): bool => $input['widget'] === 'grid');

        $expense_report_lines_to_upsert = [];

        foreach ($lines_table['rows'] as $row) {
            $expense_report_line_attributes = [
                'workday_line_id' => $row['id'],
                'expense_report_id' => $expense_report['workday_instance_id'],
            ];

            foreach ($row['cellsMap'] as $cell) {
                if ($cell['propertyName'] === 'wd:Charge_Description_Memo') {
                    $expense_report_line_attributes['memo'] = array_key_exists('value', $cell) ? $cell['value'] : null;
                } elseif ($cell['propertyName'] === 'wd:Converted_Amount') {
                    $expense_report_line_attributes['amount'] = $cell['value'];
                }
            }

            $expense_report_lines_to_upsert[] = $expense_report_line_attributes;
        }

        ExpenseReportLine::upsert(
            $expense_report_lines_to_upsert,
            [
                'workday_line_id',
                'expense_report_id',
            ],
            [
                'memo',
                'amount',
            ]
        );

        $expense_report->fill($expense_report_attributes);
        $expense_report->save();

        return response()->json($expense_report);
    }
}
