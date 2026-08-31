<?php

namespace App\Support;

use App\Enums\SellRmbStatus;
use App\Models\SellRmbTransfer;

class SellRmbSms
{
    /** Buyer SMS is sent only on submit and complete (rmb-wallet style). */
    public static function sendsToUser(SellRmbStatus $status): bool
    {
        return in_array($status, [SellRmbStatus::Submitted, SellRmbStatus::Completed], true);
    }

    public static function userMessage(SellRmbTransfer $transfer, SellRmbStatus $status, ?string $recipientName = null): string
    {
        $transfer->loadMissing(['fieldValues.field', 'user']);

        $who = self::asciiLabel($recipientName ?: $transfer->user?->name ?: 'Customer', 28);
        $ref = self::refToken($transfer);
        $rmb = self::formatRmb((float) $transfer->rmb_amount);
        $ghs = self::formatGhc((float) $transfer->ghs_payout);
        $network = self::asciiLabel(self::payoutNetwork($transfer) ?? '', 16);
        $number = self::asciiLabel(self::payoutNumber($transfer) ?? '', 16);
        $payoutTail = $network !== '' ? ' Payout via '.$network.'.' : '';

        return match ($status) {
            SellRmbStatus::Submitted => self::refLine($ref)
                .' Hi '.$who.', RMB Sell submitted. '.$rmb.' for '.$ghs.'.'.$payoutTail.' Pending Review.',
            SellRmbStatus::RmbVerification => self::refLine($ref, $transfer->id)
                .' Hi '.$who.', RMB Sell verifying. '.$rmb.' for '.$ghs.'.'.$payoutTail.' Pending Review.',
            SellRmbStatus::RmbReceived => self::refLine($ref, $transfer->id)
                .' Hi '.$who.', RMB received. '.$ghs.' payout'
                .($network !== '' ? ' via '.$network : '')
                .' preparing.',
            SellRmbStatus::PayoutProcessing, SellRmbStatus::Paid => self::refLine($ref, $transfer->id)
                .' Hi '.$who.' RMB Sell Processing. '.$ghs.' payout'
                .($network !== '' ? ' to '.$network : '')
                .' shortly.',
            SellRmbStatus::Completed => self::refLine($ref, $transfer->id)
                .' Hi '.$who.' RMB Sell Complete. '.$ghs
                .self::payoutDestinationTail($network, $number),
            SellRmbStatus::Rejected, SellRmbStatus::Failed => self::refLine($ref, $transfer->id)
                .' RMB Sell not approved. '.$rmb.' for '.$ghs.'.'
                .self::rejectionTail($transfer->rejection_reason),
            SellRmbStatus::Cancelled => self::refLine($ref, $transfer->id)
                .' Hi '.$who.', your RMB Sell request was cancelled.',
        };
    }

    public static function formatRmb(float $amount): string
    {
        return 'Rmb '.number_format($amount, 2, '.', '');
    }

    public static function formatGhc(float $amount): string
    {
        return 'Ghc'.number_format($amount, 2, '.', '');
    }

    public static function refToken(SellRmbTransfer $transfer): string
    {
        $compact = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $transfer->reference) ?? '');

        if (strlen($compact) >= 8) {
            return substr($compact, -8);
        }

        return strtoupper(str_pad((string) $transfer->id, 8, '0', STR_PAD_LEFT));
    }

    public static function refLine(string $ref, int $transferId = 0): string
    {
        $line = 'Ref '.$ref.'.';

        if ($transferId > 0) {
            $line .= ' Tx id '.$transferId.'.';
        }

        return $line;
    }

    public static function payoutNetwork(SellRmbTransfer $transfer): ?string
    {
        $value = self::fieldValue($transfer, 'payout_bank_name');

        return filled($value) ? $value : null;
    }

    public static function payoutNumber(SellRmbTransfer $transfer): ?string
    {
        $mobile = self::fieldValue($transfer, 'payout_mobile');
        if (filled($mobile)) {
            return $mobile;
        }

        $account = self::fieldValue($transfer, 'payout_account_number');

        return filled($account) ? $account : null;
    }

    private static function fieldValue(SellRmbTransfer $transfer, string $name): ?string
    {
        foreach ($transfer->fieldValues as $value) {
            if ($value->field?->name === $name && filled($value->value)) {
                return trim((string) $value->value);
            }
        }

        return null;
    }

    private static function payoutDestinationTail(string $network, string $number): string
    {
        $dest = trim($network.' '.$number);

        return $dest !== '' ? ' sent to '.$dest.'.' : ' sent to your MoMo.';
    }

    private static function rejectionTail(?string $reason): string
    {
        if (! filled($reason)) {
            return '';
        }

        $note = self::asciiLabel(strip_tags($reason), 56);

        return $note !== '' ? ' Note: '.$note : '';
    }

    private static function asciiLabel(string $value, int $max): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        if ($clean === '') {
            return '';
        }

        if (strlen($clean) <= $max) {
            return $clean;
        }

        return rtrim(substr($clean, 0, $max - 1)).'…';
    }
}
