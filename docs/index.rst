:nosearch:
:og:description: Product documentation for Loop, a web app to track, audit, and reconcile reimbursement requests
:hide-toc:

Loop
====

.. vale Google.Passive = NO
.. vale write-good.Passive = NO
.. vale write-good.E-Prime = NO

Loop is a web app for tracking, auditing, and reconciling reimbursement requests submitted to Georgia Tech.
It integrates with several systems to collect data and ultimately record transactions within QuickBooks Online.

#. Reimbursement requests are submitted to Georgia Tech via :doc:`Engage <reimbursement-requests/engage-purchase-requests>` or :doc:`email <reimbursement-requests/email-requests>`, then recorded as an :doc:`invoice <quickbooks/invoices>` within QuickBooks Online.
#. A Georgia Tech employee records the reimbursement request as an :doc:`expense report <workday/expense-reports>` within Workday.
#. Once the expense report is approved and :doc:`paid <workday/expense-payments>`, the corresponding check deposit is retrieved from Mercury.
#. The check deposit is recorded as a :doc:`payment <quickbooks/payments>` within QuickBooks Online.

Any number of reimbursement requests may be recorded as a single expense report, and any number of expense reports may be paid with a single check. Loop supports all possible combinations.

.. toctree::
   :hidden:
   :caption: Reimbursement Requests

   reimbursement-requests/engage-purchase-requests
   reimbursement-requests/email-requests
   reimbursement-requests/docusign-forms

.. toctree::
   :hidden:
   :caption: QuickBooks Online

   quickbooks/invoices
   quickbooks/payments

.. toctree::
   :hidden:
   :caption: Workday

   workday/data-load
   workday/external-committee-members
   workday/expense-reports
   workday/expense-payments
