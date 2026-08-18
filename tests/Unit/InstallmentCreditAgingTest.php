<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\InstallmentCredit\Entities\InstallmentReceivable;
use Modules\InstallmentCredit\Utils\InstallmentCreditUtil;
use PHPUnit\Framework\TestCase;

class InstallmentCreditAgingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_calendar_days_since_counts_whole_days_from_invoice_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17')->startOfDay());

        $this->assertSame(0, InstallmentReceivable::calendarDaysSince(null));
        $this->assertSame(0, InstallmentReceivable::calendarDaysSince('2026-08-17'));
        $this->assertSame(0, InstallmentReceivable::calendarDaysSince('2026-08-20'));
        $this->assertSame(30, InstallmentReceivable::calendarDaysSince('2026-07-18'));
        $this->assertSame(90, InstallmentReceivable::calendarDaysSince('2026-05-19'));
        $this->assertSame(108, InstallmentReceivable::calendarDaysSince('2026-05-01'));
    }

    public function test_aging_bucket_key_puts_90_plus_days_outside_current(): void
    {
        $this->assertSame('d30', InstallmentCreditUtil::agingBucketKey(0));
        $this->assertSame('d30', InstallmentCreditUtil::agingBucketKey(30));
        $this->assertSame('d60', InstallmentCreditUtil::agingBucketKey(31));
        $this->assertSame('d90', InstallmentCreditUtil::agingBucketKey(90));
        $this->assertSame('d120', InstallmentCreditUtil::agingBucketKey(91));
        $this->assertSame('d_gt120', InstallmentCreditUtil::agingBucketKey(121));
    }

    public function test_aging_anchor_prefers_sale_date_over_recent_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17')->startOfDay());

        $row = new InstallmentReceivable();
        $row->setRawAttributes([
            'invoice_date' => '2026-08-10',
            'due_date' => '2026-08-16',
        ]);
        $row->setRelation('transaction', (object) ['transaction_date' => '2026-05-01 14:00:00']);

        $this->assertSame('2026-05-01', $row->agingAnchorDate()->toDateString());
        $this->assertSame(108, InstallmentReceivable::calendarDaysSince($row->agingAnchorDate()));
        $this->assertSame('d120', InstallmentCreditUtil::agingBucketKey(108));
    }

    public function test_due_date_starts_the_day_after_the_invoice(): void
    {
        $this->assertSame('2026-09-18', InstallmentCreditUtil::dueDateFromInvoiceDate('2026-08-18', 30));
        $this->assertSame('2026-05-22', InstallmentCreditUtil::dueDateFromInvoiceDate('2026-05-01', 20));
        $this->assertSame('2026-08-18', InstallmentCreditUtil::dueDateFromInvoiceDate('2026-08-18', 0));
        $this->assertSame('2026-08-20', InstallmentCreditUtil::dueDateFromInvoiceDate('2026-08-18', 1));
    }

    public function test_days_due_counts_from_due_date_not_invoice_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $row = new InstallmentReceivable();
        $row->setRawAttributes([
            'invoice_date' => '2026-08-18',
            'due_date' => '2026-09-18',
        ]);

        $this->assertSame(2, $row->days_due);

        Carbon::setTestNow(Carbon::parse('2026-09-18')->startOfDay());
        $this->assertSame(0, $row->days_due);

        Carbon::setTestNow(Carbon::parse('2026-09-17')->startOfDay());
        $this->assertSame(0, $row->days_due);
    }
}
