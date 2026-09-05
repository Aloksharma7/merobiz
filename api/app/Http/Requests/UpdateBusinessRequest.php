<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $businessId = $this->route('business')?->id ?? $this->route('business');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'code' => ['sometimes', 'required', 'alpha_dash', 'max:12', Rule::unique('businesses', 'code')->ignore($businessId)],
            'business_type' => ['sometimes', 'required', 'in:product,service,digital_subscription,mixed'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'pan_number' => ['nullable', 'string', 'max:30'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['sometimes', 'required', 'alpha_dash', 'max:12'],
            'default_tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:active,archived'],
            'settings' => ['sometimes', 'array'],
            'settings.features' => ['sometimes', 'array'],
            'settings.features.installments' => ['sometimes', 'boolean'],
            'settings.dashboard' => ['sometimes', 'array'],
            'settings.dashboard.show_overview_cards' => ['sometimes', 'boolean'],
            'settings.dashboard.show_profit_breakdown' => ['sometimes', 'boolean'],
            'settings.dashboard.show_quick_actions' => ['sometimes', 'boolean'],
            'settings.dashboard.show_performance_trend' => ['sometimes', 'boolean'],
            'settings.dashboard.show_top_products' => ['sometimes', 'boolean'],
            'settings.dashboard.show_recent_invoices' => ['sometimes', 'boolean'],
            'settings.dashboard.show_expense_mix' => ['sometimes', 'boolean'],
            'settings.branding' => ['sometimes', 'array'],
            'settings.branding.tagline' => ['nullable', 'string', 'max:160'],
            'settings.branding.primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.nav_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.branding.show_logo_workspace' => ['sometimes', 'boolean'],
            'settings.branding.show_logo_invoice' => ['sometimes', 'boolean'],
            'settings.invoice' => ['sometimes', 'array'],
            'settings.invoice.company_name' => ['nullable', 'string', 'max:190'],
            'settings.invoice.website' => ['nullable', 'string', 'max:190'],
            'settings.invoice.invoice_terms' => ['nullable', 'string', 'max:1500'],
            'settings.invoice.invoice_note' => ['nullable', 'string', 'max:1000'],
            'settings.invoice.bank_name' => ['nullable', 'string', 'max:120'],
            'settings.invoice.bank_account_name' => ['nullable', 'string', 'max:150'],
            'settings.invoice.bank_account_number' => ['nullable', 'string', 'max:80'],
            'settings.invoice.bank_branch' => ['nullable', 'string', 'max:120'],
            'settings.invoice.authorized_name' => ['nullable', 'string', 'max:120'],
            'settings.invoice.authorized_title' => ['nullable', 'string', 'max:120'],
            'settings.invoice.show_seller_address' => ['sometimes', 'boolean'],
            'settings.invoice.show_seller_phone' => ['sometimes', 'boolean'],
            'settings.invoice.show_seller_email' => ['sometimes', 'boolean'],
            'settings.invoice.show_seller_website' => ['sometimes', 'boolean'],
            'settings.invoice.show_seller_pan' => ['sometimes', 'boolean'],
            'settings.invoice.show_customer_address' => ['sometimes', 'boolean'],
            'settings.invoice.show_customer_phone' => ['sometimes', 'boolean'],
            'settings.invoice.show_customer_email' => ['sometimes', 'boolean'],
            'settings.invoice.show_customer_pan' => ['sometimes', 'boolean'],
            'settings.invoice.show_due_date' => ['sometimes', 'boolean'],
            'settings.invoice.show_payment_mode' => ['sometimes', 'boolean'],
            'settings.invoice.show_prepared_by' => ['sometimes', 'boolean'],
            'settings.invoice.show_status' => ['sometimes', 'boolean'],
            'settings.invoice.show_item_discount' => ['sometimes', 'boolean'],
            'settings.invoice.show_item_tax' => ['sometimes', 'boolean'],
            'settings.invoice.show_discount_summary' => ['sometimes', 'boolean'],
            'settings.invoice.show_tax_summary' => ['sometimes', 'boolean'],
            'settings.invoice.show_amount_in_words' => ['sometimes', 'boolean'],
            'settings.invoice.show_bank_details' => ['sometimes', 'boolean'],
            'settings.invoice.show_payment_record' => ['sometimes', 'boolean'],
            'settings.invoice.show_notes' => ['sometimes', 'boolean'],
            'settings.invoice.show_terms' => ['sometimes', 'boolean'],
            'settings.invoice.show_signature' => ['sometimes', 'boolean'],
        ];
    }
}
