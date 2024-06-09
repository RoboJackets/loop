:hide-toc:
:og:description: Engage purchase requests and email requests can be loaded into QuickBooks as invoices to record the requests.

Invoices
========

.. vale Google.Headings = NO
.. vale Google.Passive = NO
.. vale Google.Will = NO
.. vale write-good.E-Prime = NO
.. vale write-good.Passive = NO
.. vale write-good.Weasel = NO

:doc:`Engage purchase requests </reimbursement-requests/engage-purchase-requests>` and :doc:`email requests </reimbursement-requests/email-requests>` can be loaded into QuickBooks as invoices to record the requests.

In QuickBooks
-------------

Due to limitations with the QuickBooks Online API, **invoices must be created in QuickBooks first, then synced from Loop.**
RoboJackets uses billable expenses to identify expenses that should be reimbursed.
See the `QuickBooks Online documentation on recording a billable expense on an invoice <https://quickbooks.intuit.com/learn-support/en-us/help-article/manage-customers/enter-billable-expenses/L37dCZU5O_US_en_US>`_.

Note that the billable expenses on an invoice should match the expenses requested for reimbursement for that invoice. This generally means you should create one invoice for **each** billable expense.

The billable expense link is the **only** step that must be completed within QuickBooks.
Loop will automatically set the invoice document number and invoice date when the invoice is synced.

In Loop
-------

Once an invoice is created within QuickBooks, you can select the corresponding billable expense from the :guilabel:`Sync to QuickBooks` action on an :doc:`Engage purchase request </reimbursement-requests/engage-purchase-requests>` or :doc:`email request </reimbursement-requests/email-requests>`.

If you created an invoice with more than one billable expense, you may need to manually adjust the invoice after syncing within Loop.
