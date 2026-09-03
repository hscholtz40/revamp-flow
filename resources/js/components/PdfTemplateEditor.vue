<script setup lang="ts">
import { getCsrfToken } from '@/lib/csrf';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import gjsPresetWebpage from 'grapesjs-preset-webpage';

interface Props {
    modelValue: string;
    module: string;
    disabled?: boolean;
    cssStyles?: string;
    imageUploadUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    cssStyles: '',
    imageUploadUrl: '/administration/pdf-templates/upload-image',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'update:cssStyles': [value: string];
}>();

const editorContainer = ref<HTMLElement | null>(null);
let editor: any = null;
const isClassesPanelCollapsed = ref(false);
const CUSTOM_STYLE_ID = 'gjs-custom-style';
const STYLES_PANEL_ID = 'styles-panel';
const CLASSES_PANEL_SELECTOR = '.gjs-clm-tags';
const TOGGLE_ICON_SELECTOR = '.toggle-icon';
const FALLBACK_EDITOR_IMAGE_SELECTOR = 'img[data-gjs-type="image"]';

function getEditorFrameDocument(): Document | null {
    const frame = editor?.Canvas?.getFrameEl?.();
    if (!frame) {
        return null;
    }

    return frame.contentDocument || (frame as any).contentWindow?.document || null;
}

function replaceFrameCustomStyle(css: string): void {
    const frameDoc = getEditorFrameDocument();
    if (!frameDoc) {
        return;
    }

    frameDoc.getElementById(CUSTOM_STYLE_ID)?.remove();

    const style = frameDoc.createElement('style');
    style.id = CUSTOM_STYLE_ID;
    style.textContent = css;
    frameDoc.head.appendChild(style);
}

function findFrameImageElement(src: string): Element | null {
    const frameDoc = getEditorFrameDocument();
    if (!frameDoc) {
        return null;
    }

    return (src ? frameDoc.querySelector(`img[src="${src}"]`) : null)
        || frameDoc.querySelector(FALLBACK_EDITOR_IMAGE_SELECTOR);
}

function markFrameImageAsUploaded(src: string): void {
    const imgEl = findFrameImageElement(src);
    if (!imgEl) {
        return;
    }

    imgEl.setAttribute('data-uploaded-image', 'true');
    if (src) {
        imgEl.setAttribute('src', src);
    }
}

function getStylesPanelElement(): HTMLElement | null {
    return document.getElementById(STYLES_PANEL_ID);
}

// Define available variables per module
const moduleVariables: Record<string, Array<{ label: string; value: string; category: string }>> = {
    invoice: [
        // Company variables
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Legal Name', value: '{{company.legal_name}}', category: 'Company' },
        { label: 'Company Tagline', value: '{{company.tagline}}', category: 'Company' },
        { label: 'Company Address', value: '{{company.address}}', category: 'Company' },
        { label: 'Company City', value: '{{company.city}}', category: 'Company' },
        { label: 'Company State', value: '{{company.state}}', category: 'Company' },
        { label: 'Company Postal Code', value: '{{company.postal_code}}', category: 'Company' },
        { label: 'Company Country', value: '{{company.country}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company Website', value: '{{company.website}}', category: 'Company' },
        { label: 'Company VAT Number', value: '{{company.vat_number}}', category: 'Company' },
        { label: 'Company Description', value: '{{company.description}}', category: 'Company' },
        { label: 'Company Logo', value: '{{company.getLogoPathForPdf()}}', category: 'Company' },
        { label: 'Invoice Footer', value: '{{company.invoice_footer}}', category: 'Company' },
        { label: 'Jobcard Footer', value: '{{company.jobcard_footer}}', category: 'Company' },
        { label: 'Quote Footer', value: '{{company.quote_footer}}', category: 'Company' },
        { label: 'PDF Footer (HTML)', value: '{{{company.pdf_footer_html}}}', category: 'Company' },
        { label: 'PDF Footer (plain text)', value: '{{company.pdf_footer}}', category: 'Company' },
        { label: 'Default Invoice Terms', value: '{{company.default_invoice_terms}}', category: 'Company' },
        { label: 'Default Quote Terms', value: '{{company.default_quote_terms}}', category: 'Company' },
        { label: 'Default Jobcard Terms', value: '{{company.default_jobcard_terms}}', category: 'Company' },
        // Invoice variables
        { label: 'Invoice Number', value: '{{invoice.invoice_number}}', category: 'Invoice' },
        { label: 'Invoice Title', value: '{{invoice.title}}', category: 'Invoice' },
        { label: 'Invoice Description', value: '{{invoice.description}}', category: 'Invoice' },
        { label: 'Invoice Date', value: '{{invoice.invoice_date}}', category: 'Invoice' },
        { label: 'Due Date', value: '{{invoice.due_date}}', category: 'Invoice' },
        { label: 'Invoice Status', value: '{{invoice.status}}', category: 'Invoice' },
        { label: 'Subtotal', value: '{{invoice.subtotal}}', category: 'Invoice' },
        { label: 'Tax Amount', value: '{{invoice.tax_amount}}', category: 'Invoice' },
        { label: 'Document title (Tax Invoice / Invoice)', value: '{{invoice.document_title}}', category: 'Invoice' },
        { label: 'Total', value: '{{invoice.total}}', category: 'Invoice' },
        { label: 'Notes', value: '{{invoice.notes}}', category: 'Invoice' },
        { label: 'Payment terms (COD, Net 30, etc.)', value: '{{invoice.terms}}', category: 'Invoice' },
        { label: 'Terms & Conditions (body text)', value: '{{invoice.terms_conditions}}', category: 'Invoice' },
        // Customer variables
        { label: 'Customer Name', value: '{{invoice.customer.name}}', category: 'Customer' },
        { label: 'Customer Account Code', value: '{{invoice.customer.account_code}}', category: 'Customer' },
        { label: 'Customer Address', value: '{{invoice.customer.address}}', category: 'Customer' },
        { label: 'Customer City', value: '{{invoice.customer.city}}', category: 'Customer' },
        { label: 'Customer Email', value: '{{invoice.customer.email}}', category: 'Customer' },
        { label: 'Customer Phone', value: '{{invoice.customer.phone}}', category: 'Customer' },
        { label: 'Customer VAT Number', value: '{{invoice.customer.vat_number}}', category: 'Customer' },
        // Line Items
        { label: 'Line Items Loop', value: '{{#each invoice.lineItems}}{{this.description_with_code}}{{/each}}', category: 'Line Items' },
    ],
    quote: [
        // Company variables (same as invoice)
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Legal Name', value: '{{company.legal_name}}', category: 'Company' },
        { label: 'Company Tagline', value: '{{company.tagline}}', category: 'Company' },
        { label: 'Company Address', value: '{{company.address}}', category: 'Company' },
        { label: 'Company City', value: '{{company.city}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company VAT Number', value: '{{company.vat_number}}', category: 'Company' },
        { label: 'Company Logo', value: '{{company.getLogoPathForPdf()}}', category: 'Company' },
        { label: 'Invoice Footer', value: '{{company.invoice_footer}}', category: 'Company' },
        { label: 'Jobcard Footer', value: '{{company.jobcard_footer}}', category: 'Company' },
        { label: 'Quote Footer', value: '{{company.quote_footer}}', category: 'Company' },
        { label: 'PDF Footer (HTML)', value: '{{{company.pdf_footer_html}}}', category: 'Company' },
        { label: 'PDF Footer (plain text)', value: '{{company.pdf_footer}}', category: 'Company' },
        // Quote variables
        { label: 'Quote Number', value: '{{quote.quote_number}}', category: 'Quote' },
        { label: 'Quote Title', value: '{{quote.title}}', category: 'Quote' },
        { label: 'Quote Description', value: '{{quote.description}}', category: 'Quote' },
        { label: 'Quote Date', value: '{{quote.quote_date}}', category: 'Quote' },
        { label: 'Expiry Date', value: '{{quote.expiry_date}}', category: 'Quote' },
        { label: 'Quote Status', value: '{{quote.status}}', category: 'Quote' },
        { label: 'Subtotal', value: '{{quote.subtotal}}', category: 'Quote' },
        { label: 'Tax Amount', value: '{{quote.tax_amount}}', category: 'Quote' },
        { label: 'Total', value: '{{quote.total}}', category: 'Quote' },
        { label: 'Notes', value: '{{quote.notes}}', category: 'Quote' },
        { label: 'Terms', value: '{{quote.terms_conditions}}', category: 'Quote' },
        // Customer variables
        { label: 'Customer Name', value: '{{quote.customer.name}}', category: 'Customer' },
        { label: 'Customer Account Code', value: '{{quote.customer.account_code}}', category: 'Customer' },
        { label: 'Customer Address', value: '{{quote.customer.address}}', category: 'Customer' },
        { label: 'Customer City', value: '{{quote.customer.city}}', category: 'Customer' },
        { label: 'Customer Email', value: '{{quote.customer.email}}', category: 'Customer' },
        { label: 'Customer Phone', value: '{{quote.customer.phone}}', category: 'Customer' },
        { label: 'Customer VAT Number', value: '{{quote.customer.vat_number}}', category: 'Customer' },
        // Line Items
        { label: 'Line Items Loop', value: '{{#each quote.lineItems}}{{this.description_with_code}}{{/each}}', category: 'Line Items' },
    ],
    jobcard: [
        // Company variables
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Legal Name', value: '{{company.legal_name}}', category: 'Company' },
        { label: 'Company Tagline', value: '{{company.tagline}}', category: 'Company' },
        { label: 'Company Address', value: '{{company.address}}', category: 'Company' },
        { label: 'Company City', value: '{{company.city}}', category: 'Company' },
        { label: 'Company State', value: '{{company.state}}', category: 'Company' },
        { label: 'Company Postal Code', value: '{{company.postal_code}}', category: 'Company' },
        { label: 'Company Country', value: '{{company.country}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company Website', value: '{{company.website}}', category: 'Company' },
        { label: 'Company VAT Number', value: '{{company.vat_number}}', category: 'Company' },
        { label: 'Company Description', value: '{{company.description}}', category: 'Company' },
        { label: 'Company Logo', value: '{{company.getLogoPathForPdf()}}', category: 'Company' },
        { label: 'Invoice Footer', value: '{{company.invoice_footer}}', category: 'Company' },
        { label: 'Jobcard Footer', value: '{{company.jobcard_footer}}', category: 'Company' },
        { label: 'Quote Footer', value: '{{company.quote_footer}}', category: 'Company' },
        { label: 'PDF Footer (HTML)', value: '{{{company.pdf_footer_html}}}', category: 'Company' },
        { label: 'PDF Footer (plain text)', value: '{{company.pdf_footer}}', category: 'Company' },
        { label: 'Default Invoice Terms', value: '{{company.default_invoice_terms}}', category: 'Company' },
        { label: 'Default Quote Terms', value: '{{company.default_quote_terms}}', category: 'Company' },
        { label: 'Default Jobcard Terms', value: '{{company.default_jobcard_terms}}', category: 'Company' },
        // Jobcard variables
        { label: 'Job Number', value: '{{jobcard.job_number}}', category: 'Jobcard' },
        { label: 'Jobcard Title', value: '{{jobcard.title}}', category: 'Jobcard' },
        { label: 'Jobcard Description', value: '{{jobcard.description}}', category: 'Jobcard' },
        { label: 'Start Date', value: '{{jobcard.start_date}}', category: 'Jobcard' },
        { label: 'Due Date', value: '{{jobcard.due_date}}', category: 'Jobcard' },
        { label: 'Status', value: '{{jobcard.status}}', category: 'Jobcard' },
        { label: 'Subtotal', value: '{{jobcard.subtotal}}', category: 'Jobcard' },
        { label: 'Tax Amount', value: '{{jobcard.tax_amount}}', category: 'Jobcard' },
        { label: 'Total', value: '{{jobcard.total}}', category: 'Jobcard' },
        { label: 'Jobcard Notes', value: '{{jobcard.notes}}', category: 'Jobcard' },
        { label: 'Jobcard Terms', value: '{{jobcard.terms_conditions}}', category: 'Jobcard' },
        // Customer variables
        { label: 'Customer Name', value: '{{jobcard.customer.name}}', category: 'Customer' },
        { label: 'Customer Account Code', value: '{{jobcard.customer.account_code}}', category: 'Customer' },
        { label: 'Customer Address', value: '{{jobcard.customer.address}}', category: 'Customer' },
        { label: 'Customer City', value: '{{jobcard.customer.city}}', category: 'Customer' },
        { label: 'Customer Email', value: '{{jobcard.customer.email}}', category: 'Customer' },
        { label: 'Customer Phone', value: '{{jobcard.customer.phone}}', category: 'Customer' },
        { label: 'Customer VAT Number', value: '{{jobcard.customer.vat_number}}', category: 'Customer' },
        // Line Items
        { label: 'Line Items Loop', value: '{{#each jobcard.lineItems}}{{this.description_with_code}}{{/each}}', category: 'Line Items' },
    ],
    'proforma-invoice': [
        // Same as quote
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Legal Name', value: '{{company.legal_name}}', category: 'Company' },
        { label: 'Company Tagline', value: '{{company.tagline}}', category: 'Company' },
        { label: 'Company Address', value: '{{company.address}}', category: 'Company' },
        { label: 'Company City', value: '{{company.city}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company VAT Number', value: '{{company.vat_number}}', category: 'Company' },
        { label: 'Company Logo', value: '{{company.getLogoPathForPdf()}}', category: 'Company' },
        { label: 'Invoice Footer', value: '{{company.invoice_footer}}', category: 'Company' },
        { label: 'Jobcard Footer', value: '{{company.jobcard_footer}}', category: 'Company' },
        { label: 'Quote Footer', value: '{{company.quote_footer}}', category: 'Company' },
        { label: 'PDF Footer (HTML)', value: '{{{company.pdf_footer_html}}}', category: 'Company' },
        { label: 'PDF Footer (plain text)', value: '{{company.pdf_footer}}', category: 'Company' },
        // Quote variables (proforma uses quote data)
        { label: 'Quote Number', value: '{{quote.quote_number}}', category: 'Quote' },
        { label: 'Quote Title', value: '{{quote.title}}', category: 'Quote' },
        { label: 'Quote Description', value: '{{quote.description}}', category: 'Quote' },
        { label: 'Quote Date', value: '{{quote.quote_date}}', category: 'Quote' },
        { label: 'Expiry Date', value: '{{quote.expiry_date}}', category: 'Quote' },
        { label: 'Quote Status', value: '{{quote.status}}', category: 'Quote' },
        { label: 'Subtotal', value: '{{quote.subtotal}}', category: 'Quote' },
        { label: 'Tax Amount', value: '{{quote.tax_amount}}', category: 'Quote' },
        { label: 'Total', value: '{{quote.total}}', category: 'Quote' },
        { label: 'Notes', value: '{{quote.notes}}', category: 'Quote' },
        { label: 'Terms', value: '{{quote.terms_conditions}}', category: 'Quote' },
        // Customer variables
        { label: 'Customer Name', value: '{{quote.customer.name}}', category: 'Customer' },
        { label: 'Customer Account Code', value: '{{quote.customer.account_code}}', category: 'Customer' },
        { label: 'Customer Address', value: '{{quote.customer.address}}', category: 'Customer' },
        { label: 'Customer City', value: '{{quote.customer.city}}', category: 'Customer' },
        { label: 'Customer Email', value: '{{quote.customer.email}}', category: 'Customer' },
        { label: 'Customer Phone', value: '{{quote.customer.phone}}', category: 'Customer' },
        { label: 'Customer VAT Number', value: '{{quote.customer.vat_number}}', category: 'Customer' },
        // Line Items
        { label: 'Line Items Loop', value: '{{#each quote.lineItems}}{{this.description_with_code}}{{/each}}', category: 'Line Items' },
    ],
    'purchase-order': [
        // Company variables
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Legal Name', value: '{{company.legal_name}}', category: 'Company' },
        { label: 'Company Tagline', value: '{{company.tagline}}', category: 'Company' },
        { label: 'Company Address', value: '{{company.address}}', category: 'Company' },
        { label: 'Company City', value: '{{company.city}}', category: 'Company' },
        { label: 'Company State', value: '{{company.state}}', category: 'Company' },
        { label: 'Company Postal Code', value: '{{company.postal_code}}', category: 'Company' },
        { label: 'Company Country', value: '{{company.country}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company Website', value: '{{company.website}}', category: 'Company' },
        { label: 'Company VAT Number', value: '{{company.vat_number}}', category: 'Company' },
        { label: 'Company Description', value: '{{company.description}}', category: 'Company' },
        { label: 'Company Logo', value: '{{company.logo_path_for_pdf}}', category: 'Company' },
        // Purchase Order variables
        { label: 'PO Number', value: '{{purchaseOrder.po_number}}', category: 'Purchase Order' },
        { label: 'Order Date', value: '{{purchaseOrder.order_date}}', category: 'Purchase Order' },
        { label: 'Expected Delivery Date', value: '{{purchaseOrder.expected_delivery_date}}', category: 'Purchase Order' },
        { label: 'Received Date', value: '{{purchaseOrder.received_date}}', category: 'Purchase Order' },
        { label: 'Status', value: '{{purchaseOrder.status}}', category: 'Purchase Order' },
        { label: 'Subtotal', value: '{{purchaseOrder.subtotal}}', category: 'Purchase Order' },
        { label: 'Tax Amount', value: '{{purchaseOrder.tax_amount}}', category: 'Purchase Order' },
        { label: 'Total', value: '{{purchaseOrder.total}}', category: 'Purchase Order' },
        { label: 'Notes', value: '{{purchaseOrder.notes}}', category: 'Purchase Order' },
        { label: 'Terms', value: '{{purchaseOrder.terms}}', category: 'Purchase Order' },
        // Supplier variables
        { label: 'Supplier Name', value: '{{purchaseOrder.supplier.name}}', category: 'Supplier' },
        { label: 'Supplier Address', value: '{{purchaseOrder.supplier.address}}', category: 'Supplier' },
        { label: 'Supplier City', value: '{{purchaseOrder.supplier.city}}', category: 'Supplier' },
        { label: 'Supplier Postal Code', value: '{{purchaseOrder.supplier.postal_code}}', category: 'Supplier' },
        { label: 'Supplier Phone', value: '{{purchaseOrder.supplier.phone}}', category: 'Supplier' },
        { label: 'Supplier Email', value: '{{purchaseOrder.supplier.email}}', category: 'Supplier' },
        // Line Items
        { label: 'Line Items Loop', value: '{{#each purchaseOrder.items}}{{this.description_with_code}}{{/each}}', category: 'Line Items' },
    ],
    'customer-contact': [
        { label: 'Company Name', value: '{{company.name}}', category: 'Company' },
        { label: 'Company Email', value: '{{company.email}}', category: 'Company' },
        { label: 'Company Phone', value: '{{company.phone}}', category: 'Company' },
        { label: 'Company Website', value: '{{company.website}}', category: 'Company' },
        { label: 'Customer Name', value: '{{customer.name}}', category: 'Customer' },
        { label: 'Customer Email', value: '{{customer.email}}', category: 'Customer' },
        { label: 'Customer Phone', value: '{{customer.phone}}', category: 'Customer' },
        { label: 'Customer Account Code', value: '{{customer.account_code}}', category: 'Customer' },
        { label: 'Contact Name', value: '{{contact.name}}', category: 'Contact' },
        { label: 'Contact Email', value: '{{contact.email}}', category: 'Contact' },
        { label: 'Contact Phone', value: '{{contact.phone}}', category: 'Contact' },
        { label: 'Contact Position', value: '{{contact.position}}', category: 'Contact' },
        { label: 'Current User Name', value: '{{user.name}}', category: 'User' },
        { label: 'Current User Email', value: '{{user.email}}', category: 'User' },
        { label: 'Today Date', value: '{{date.today}}', category: 'Dates' },
        { label: 'Current Date Time', value: '{{date.now}}', category: 'Dates' },
    ],
};

const availableVariables = ref(moduleVariables[props.module] || []);

// Group variables by category
const groupedVariables = computed(() => {
    const groups: Record<string, typeof availableVariables.value> = {};
    availableVariables.value.forEach((variable) => {
        if (!groups[variable.category]) {
            groups[variable.category] = [];
        }
        groups[variable.category].push(variable);
    });
    return groups;
});

// Default templates for each module (converted from Blade to Handlebars)
const defaultTemplates: Record<string, { html: string; css: string }> = {
    invoice: {
        css: `@page {
    margin-bottom: 70px;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}
.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}
.document-title {
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}
.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.company-logo {
    max-width: 240px;
    max-height: 120px;
    margin-bottom: 10px;
}
.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}
.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}
.company-details {
    font-size: 11px;
    line-height: 1.3;
}
.company-details p {
    margin: 2px 0;
}
.banking-details {
    text-align: left;
    font-size: 11px;
}
.banking-details h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.banking-details p {
    margin: 2px 0;
}
.customer-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.customer-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.customer-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: right;
}
.customer-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.customer-info p {
    margin: 2px 0;
    font-size: 11px;
}
.vat-number {
    font-size: 11px;
}
.vat-number label {
    font-weight: bold;
}
.separator-line {
    border-top: 1px solid #000;
    margin: 15px 0;
}
.invoice-details {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.invoice-detail {
    display: table-cell;
    width: 20%;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    font-size: 11px;
}
.invoice-detail-label {
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
}
.invoice-detail-value {
    color: #000;
}
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.line-items-table th,
.line-items-table td {
    border-bottom: 1px solid #000;
    padding: 8px;
    font-size: 11px;
}
.line-items-table th {
    font-weight: bold;
    text-align: left;
    border-bottom: 2px solid #000;
}
.line-items-table td {
    text-align: left;
}
.line-items-table .text-right {
    text-align: right;
}
.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}
.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}
.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}
.total-row span:first-child {
    text-align: left;
}
.total-row span:last-child {
    text-align: right;
}
.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}
.terms-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}
.terms-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.terms-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}
.signature-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
}
.signature-row {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.signature-item {
    display: table-cell;
    width: 33.33%;
    text-align: left;
    font-size: 11px;
}
.signature-line {
    border-bottom: 1px dashed #000;
    height: 20px;
    margin-top: 5px;
}
.payment-clause {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: center;
}
.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    background: #fff;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    text-align: left;
}
.footer-right {
    display: table-cell;
    text-align: right;
}`,
        html: `<div class="page-header">Page 1 of 1</div>
<div class="document-title">{{invoice.document_title}}</div>
<div class="company-section">
    <div class="company-left">
        <img src="{{company.getLogoPathForPdf()}}" alt="Company Logo" class="company-logo">
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.tagline}}</div>
        <div class="company-details">
            <p><strong>{{company.legal_name}}</strong></p>
            <p>{{company.address}}</p>
            <p>{{company.city}}</p>
            <p>VAT Number {{company.vat_number}}</p>
            <p>Telephone {{company.phone}}</p>
            <p>Fax {{company.fax}}</p>
        </div>
    </div>
    <div class="company-right">
        <div class="banking-details">
            <h4>Banking Details</h4>
            <p><strong>{{company.bank_account_name}}</strong></p>
            <p>Bank Name: {{company.bank_name}}</p>
            <p>Acc No: {{company.bank_account_number}}</p>
            <p>Sort Code: {{company.bank_sort_code}}</p>
        </div>
    </div>
</div>
<div class="customer-section">
    <div class="customer-left">
        <div class="customer-info">
            <h4>To:</h4>
            <p><strong>{{invoice.customer.account_code}}</strong></p>
            <p><strong>{{invoice.customer.name}}</strong></p>
            <p>{{invoice.customer.address}}</p>
            <p>{{invoice.customer.city}}</p>
            <p>{{invoice.customer.postal_code}}</p>
        </div>
    </div>
    <div class="customer-right">
        <div class="vat-number">
            <label>Customer Vat Number</label>
            <div style="border: 1px solid #000; min-height: 20px; margin-top: 5px; padding: 2px 4px;">{{invoice.customer.vat_number}}</div>
        </div>
    </div>
</div>
<div class="separator-line"></div>
<div class="invoice-details">
    <div class="invoice-detail">
        <span class="invoice-detail-label">Account</span>
        <span class="invoice-detail-value">{{invoice.customer.account_code}}</span>
    </div>
    <div class="invoice-detail">
        <span class="invoice-detail-label">Date</span>
        <span class="invoice-detail-value">{{invoice.invoice_date}}</span>
    </div>
    <div class="invoice-detail">
        <span class="invoice-detail-label">Order No</span>
        <span class="invoice-detail-value">{{invoice.order_number}}</span>
    </div>
    <div class="invoice-detail">
        <span class="invoice-detail-label">Job No</span>
        <span class="invoice-detail-value">{{invoice.job_number}}</span>
    </div>
    <div class="invoice-detail">
        <span class="invoice-detail-label">Invoice No</span>
        <span class="invoice-detail-value">{{invoice.invoice_number}}</span>
    </div>
</div>
<table class="line-items-table">
    <thead>
        <tr>
            <th>Item Code</th>
            <th>Item Description</th>
            <th class="text-right">QTY</th>
            <th class="text-right">Price (Ex)</th>
            <th class="text-right">Disc %</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total (Excl)</th>
        </tr>
    </thead>
    <tbody><tr data-handlebars-loop-start="{{#each invoice.lineItems}}" data-handlebars-loop-end="{{/each}}">
            <td>{{this.product.sku}}</td>
            <td>{{this.description_with_code}}</td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R{{this.unit_price}}</td>
            <td class="text-right">{{this.discount_percentage}}%</td>
            <td class="text-right">R{{this.tax}}</td>
            <td class="text-right">R{{this.total}}</td>
        </tr></tbody>
</table>
<div class="totals-section">
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{invoice.subtotal}}</span>
    </div>
    <div class="total-row">
        <span>Tax:</span>
        <span>R{{invoice.tax_amount}}</span>
    </div>
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{invoice.total}}</span>
    </div>
    <div class="total-row">
        <span>Discount:</span>
        <span>R{{invoice.discount_amount}}</span>
    </div>
    <div class="total-row">
        <span>Rounding:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row">
        <span>Less: Excess:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row final">
        <span>Total (Excl):</span>
        <span>R{{invoice.total}}</span>
    </div>
</div>
<div class="terms-section">
    <h4>Description</h4>
    <p>{{invoice.description}}</p>
    <h4>Notes</h4>
    <p>{{invoice.notes}}</p>
    <h4>Terms & Conditions</h4>
    <p>{{invoice.terms_conditions}}</p>
</div>
<div class="signature-section">
    <div class="signature-row">
        <div class="signature-item">
            <div>Received by</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Date</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Signed</div>
            <div class="signature-line"></div>
        </div>
    </div>
</div>
<div class="footer">
    <div class="footer-left">
        {{{company.pdf_footer_html}}}
    </div>
    <div class="footer-right">
        {{date}}
    </div>
</div>`,
    },
    quote: {
        css: `@page {
    margin-bottom: 70px;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}
.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}
.document-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}
.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.company-logo {
    max-width: 220px;
    max-height: 110px;
    margin-bottom: 10px;
}
.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}
.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}
.company-details {
    font-size: 11px;
    line-height: 1.3;
}
.company-details p {
    margin: 2px 0;
}
.banking-details {
    text-align: left;
    font-size: 11px;
}
.banking-details h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.banking-details p {
    margin: 2px 0;
}
.customer-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.customer-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.customer-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: right;
}
.customer-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.customer-info p {
    margin: 2px 0;
    font-size: 11px;
}
.validity-period {
    font-size: 11px;
}
.validity-period label {
    font-weight: bold;
}
.separator-line {
    border-top: 1px solid #000;
    margin: 15px 0;
}
.quote-details {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.quote-detail {
    display: table-cell;
    width: 25%;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    font-size: 11px;
}
.quote-detail-label {
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
}
.quote-detail-value {
    color: #000;
}
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.line-items-table th,
.line-items-table td {
    border-bottom: 1px solid #000;
    padding: 8px;
    font-size: 11px;
}
.line-items-table th {
    font-weight: bold;
    text-align: left;
    border-bottom: 2px solid #000;
}
.line-items-table td {
    text-align: left;
}
.line-items-table .text-right {
    text-align: right;
}
.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}
.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}
.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}
.total-row span:first-child {
    text-align: left;
}
.total-row span:last-child {
    text-align: right;
}
.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}
.terms-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}
.terms-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.terms-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}
.signature-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
}
.signature-row {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.signature-item {
    display: table-cell;
    width: 33.33%;
    text-align: left;
    font-size: 11px;
}
.signature-line {
    border-bottom: 1px dashed #000;
    height: 20px;
    margin-top: 5px;
}
.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    background: #fff;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    text-align: left;
}
.footer-right {
    display: table-cell;
    text-align: right;
}`,
        html: `<div class="page-header">Page 1 of 1</div>
<div class="document-title">Quote</div>
<div class="company-section">
    <div class="company-left">
        <img src="{{company.getLogoPathForPdf()}}" alt="Company Logo" class="company-logo">
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.tagline}}</div>
        <div class="company-details">
            <p><strong>{{company.legal_name}}</strong></p>
            <p>{{company.address}}</p>
            <p>{{company.city}}</p>
            <p>VAT Number {{company.vat_number}}</p>
            <p>Telephone {{company.phone}}</p>
            <p>Fax {{company.fax}}</p>
        </div>
    </div>
    <div class="company-right">
        <div class="banking-details">
            <h4>Banking Details</h4>
            <p><strong>{{company.bank_account_name}}</strong></p>
            <p>Bank Name: {{company.bank_name}}</p>
            <p>Acc No: {{company.bank_account_number}}</p>
            <p>Sort Code: {{company.bank_sort_code}}</p>
        </div>
    </div>
</div>
<div class="customer-section">
    <div class="customer-left">
        <div class="customer-info">
            <h4>To:</h4>
            <p><strong>{{quote.customer.account_code}}</strong></p>
            <p><strong>{{quote.customer.name}}</strong></p>
            <p>{{quote.customer.address}}</p>
            <p>{{quote.customer.city}}</p>
            <p>{{quote.customer.postal_code}}</p>
        </div>
    </div>
    <div class="customer-right">
        <div class="validity-period">
            <label>Valid Until</label>
            <div style="border: 1px solid #000; height: 20px; margin-top: 5px; padding: 2px;">{{quote.expiry_date}}</div>
        </div>
    </div>
</div>
<div class="separator-line"></div>
<div class="quote-details">
    <div class="quote-detail">
        <span class="quote-detail-label">Account</span>
        <span class="quote-detail-value">{{quote.customer.account_code}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Date</span>
        <span class="quote-detail-value">{{quote.created_at}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Order No</span>
        <span class="quote-detail-value">{{quote.order_number}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Quote No</span>
        <span class="quote-detail-value">{{quote.quote_number}}</span>
    </div>
</div>
<table class="line-items-table">
    <thead>
        <tr>
            <th>Item Description</th>
            <th class="text-right">QTY</th>
            <th class="text-right">Price (Ex)</th>
            <th class="text-right">Disc %</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total (Excl)</th>
        </tr>
    </thead>
    <tbody><!-- {{#each quote.lineItems}} --><tr>
            <td>{{this.description_with_code}}</td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R{{this.unit_price}}</td>
            <td class="text-right">{{this.discount_percentage}}%</td>
            <td class="text-right">R{{this.tax}}</td>
            <td class="text-right">R{{this.total}}</td>
        </tr><!-- {{/each}} --></tbody>
</table>
<div class="totals-section">
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{quote.subtotal}}</span>
    </div>
    <div class="total-row">
        <span>Tax:</span>
        <span>R{{quote.tax_amount}}</span>
    </div>
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{quote.total}}</span>
    </div>
    <div class="total-row">
        <span>Discount:</span>
        <span>R{{quote.discount_amount}}</span>
    </div>
    <div class="total-row">
        <span>Rounding:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row">
        <span>Less: Excess:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row final">
        <span>Total (Excl):</span>
        <span>R{{quote.total}}</span>
    </div>
</div>
<div class="terms-section">
    <h4>Description</h4>
    <p>{{quote.description}}</p>
    <h4>Notes</h4>
    <p>{{quote.notes}}</p>
    <h4>Terms & Conditions</h4>
    <p>This quote is valid for 30 days from the date of issue.</p>
    <p>Prices are subject to change without notice.</p>
    <p>Payment terms: Net 30 days from invoice date.</p>
    <p>{{quote.terms_conditions}}</p>
</div>
<div class="signature-section">
    <div class="signature-row">
        <div class="signature-item">
            <div>Received by</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Date</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Signature</div>
            <div class="signature-line"></div>
        </div>
    </div>
</div>
<div class="footer">
    <div class="footer-left">
        {{{company.pdf_footer_html}}}
    </div>
    <div class="footer-right">
        {{date}}
    </div>
</div>`,
    },
    jobcard: {
        css: `@page {
    margin-bottom: 70px;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}
.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}
.document-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}
.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.company-logo {
    max-width: 220px;
    max-height: 110px;
    margin-bottom: 10px;
}
.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}
.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}
.company-details {
    font-size: 11px;
    line-height: 1.3;
}
.company-details p {
    margin: 2px 0;
}
.banking-details {
    text-align: left;
    font-size: 11px;
}
.banking-details h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.banking-details p {
    margin: 2px 0;
}
.customer-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.customer-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.customer-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: right;
}
.customer-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.customer-info p {
    margin: 2px 0;
    font-size: 11px;
}
.priority-level {
    font-size: 11px;
}
.priority-level label {
    font-weight: bold;
}
.separator-line {
    border-top: 1px solid #000;
    margin: 15px 0;
}
.jobcard-details {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.jobcard-detail {
    display: table-cell;
    width: 20%;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    font-size: 11px;
}
.jobcard-detail-label {
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
}
.jobcard-detail-value {
    color: #000;
}
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.line-items-table th,
.line-items-table td {
    border-bottom: 1px solid #000;
    padding: 8px;
    font-size: 11px;
}
.line-items-table th {
    font-weight: bold;
    text-align: left;
    border-bottom: 2px solid #000;
}
.line-items-table td {
    text-align: left;
}
.line-items-table .text-right {
    text-align: right;
}
.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}
.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}
.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}
.total-row span:first-child {
    text-align: left;
}
.total-row span:last-child {
    text-align: right;
}
.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}
.work-notes-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}
.work-notes-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.work-notes-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}
.signature-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #000;
}
.signature-row {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.signature-item {
    display: table-cell;
    width: 33.33%;
    text-align: left;
    font-size: 11px;
}
.signature-line {
    border-bottom: 1px dashed #000;
    height: 20px;
    margin-top: 5px;
}
.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    background: #fff;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    text-align: left;
}
.footer-right {
    display: table-cell;
    text-align: right;
}`,
        html: `<div class="page-header">Page 1 of 1</div>
<div class="document-title">Job Card</div>
<div class="company-section">
    <div class="company-left">
        <img src="{{company.getLogoPathForPdf()}}" alt="Company Logo" class="company-logo">
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.tagline}}</div>
        <div class="company-details">
            <p><strong>{{company.legal_name}}</strong></p>
            <p>{{company.address}}</p>
            <p>{{company.city}}</p>
            <p>VAT Number {{company.vat_number}}</p>
            <p>Telephone {{company.phone}}</p>
            <p>Fax {{company.fax}}</p>
        </div>
    </div>
    <div class="company-right">
        <div class="banking-details">
            <h4>Banking Details</h4>
            <p><strong>{{company.bank_account_name}}</strong></p>
            <p>Bank Name: {{company.bank_name}}</p>
            <p>Acc No: {{company.bank_account_number}}</p>
            <p>Sort Code: {{company.bank_sort_code}}</p>
        </div>
    </div>
</div>
<div class="customer-section">
    <div class="customer-left">
        <div class="customer-info">
            <h4>To:</h4>
            <p><strong>{{jobcard.customer.account_code}}</strong></p>
            <p><strong>{{jobcard.customer.name}}</strong></p>
            <p>{{jobcard.customer.address}}</p>
            <p>{{jobcard.customer.city}}</p>
            <p>{{jobcard.customer.postal_code}}</p>
        </div>
    </div>
    <div class="customer-right">
        <div class="priority-level">
            <label>Priority Level</label>
            <div style="border: 1px solid #000; height: 20px; margin-top: 5px; padding: 2px;">{{jobcard.priority}}</div>
        </div>
    </div>
</div>
<div class="separator-line"></div>
<div class="jobcard-details">
    <div class="jobcard-detail">
        <span class="jobcard-detail-label">Account</span>
        <span class="jobcard-detail-value">{{jobcard.customer.account_code}}</span>
    </div>
    <div class="jobcard-detail">
        <span class="jobcard-detail-label">Date</span>
        <span class="jobcard-detail-value">{{jobcard.created_at}}</span>
    </div>
    <div class="jobcard-detail">
        <span class="jobcard-detail-label">Order No</span>
        <span class="jobcard-detail-value">{{jobcard.order_number}}</span>
    </div>
    <div class="jobcard-detail">
        <span class="jobcard-detail-label">Job No</span>
        <span class="jobcard-detail-value">{{jobcard.job_number}}</span>
    </div>
    <div class="jobcard-detail">
        <span class="jobcard-detail-label">Due Date</span>
        <span class="jobcard-detail-value">{{jobcard.due_date}}</span>
    </div>
</div>
<table class="line-items-table">
    <thead>
        <tr>
            <th>Item Code</th>
            <th>Work Description</th>
            <th class="text-right">QTY</th>
            <th class="text-right">Rate</th>
            <th class="text-right">Hours</th>
            <th class="text-right">Total (Excl)</th>
        </tr>
    </thead>
    <tbody>
        <tr data-handlebars-loop-start="{{#each jobcard.lineItems}}" data-handlebars-loop-end="{{/each}}">
            <td>{{this.product.sku}}</td>
            <td>{{this.description_with_code}}</td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R{{this.unit_price}}</td>
            <td class="text-right">{{this.hours}}</td>
            <td class="text-right">R{{this.total}}</td>
        </tr>
    </tbody>
</table>
<div class="totals-section">
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{jobcard.subtotal}}</span>
    </div>
    <div class="total-row">
        <span>Tax:</span>
        <span>R{{jobcard.tax_amount}}</span>
    </div>
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{jobcard.total}}</span>
    </div>
    <div class="total-row">
        <span>Discount:</span>
        <span>R{{jobcard.discount_amount}}</span>
    </div>
    <div class="total-row">
        <span>Rounding:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row">
        <span>Less: Excess:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row final">
        <span>Total (Excl):</span>
        <span>R{{jobcard.total}}</span>
    </div>
</div>
<div class="work-notes-section">
    <h4>Work Notes & Instructions</h4>
    <p><strong>Job Description:</strong> {{jobcard.description}}</p>
    <p><strong>Additional Notes:</strong> {{jobcard.notes}}</p>
    <p><strong>Terms & Conditions:</strong> {{jobcard.terms_conditions}}</p>
</div>
<div class="signature-section">
    <div class="signature-row">
        <div class="signature-item">
            <div>Technician</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Date Completed</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Customer Signature</div>
            <div class="signature-line"></div>
        </div>
    </div>
</div>
<div class="footer">
    <div class="footer-left">
        {{{company.pdf_footer_html}}}
    </div>
    <div class="footer-right">
        {{date}}
    </div>
</div>`,
    },
    'proforma-invoice': {
        css: `@page {
    margin-bottom: 70px;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}
.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}
.document-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}
.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.company-logo {
    max-width: 220px;
    max-height: 110px;
    margin-bottom: 10px;
}
.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}
.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}
.company-details {
    font-size: 11px;
    line-height: 1.3;
}
.company-details p {
    margin: 2px 0;
}
.banking-details {
    text-align: left;
    font-size: 11px;
}
.banking-details h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.banking-details p {
    margin: 2px 0;
}
.customer-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.customer-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.customer-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: right;
}
.customer-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.customer-info p {
    margin: 2px 0;
    font-size: 11px;
}
.validity-period {
    font-size: 11px;
}
.validity-period label {
    font-weight: bold;
}
.separator-line {
    border-top: 1px solid #000;
    margin: 15px 0;
}
.quote-details {
    display: table;
    width: 100%;
    margin-bottom: 20px;
}
.quote-detail {
    display: table-cell;
    width: 25%;
    border: 1px solid #000;
    padding: 8px;
    text-align: center;
    font-size: 11px;
}
.quote-detail-label {
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
}
.quote-detail-value {
    color: #000;
}
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.line-items-table th,
.line-items-table td {
    border-bottom: 1px solid #000;
    padding: 8px;
    font-size: 11px;
}
.line-items-table th {
    font-weight: bold;
    text-align: left;
    border-bottom: 2px solid #000;
}
.line-items-table td {
    text-align: left;
}
.line-items-table .text-right {
    text-align: right;
}
.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}
.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}
.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}
.total-row span:first-child {
    text-align: left;
}
.total-row span:last-child {
    text-align: right;
}
.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}
.terms-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}
.terms-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.terms-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}
.signature-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
}
.signature-row {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.signature-item {
    display: table-cell;
    width: 33.33%;
    text-align: left;
    font-size: 11px;
}
.signature-line {
    border-bottom: 1px dashed #000;
    height: 20px;
    margin-top: 5px;
}
.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    background: #fff;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    text-align: left;
}
.footer-right {
    display: table-cell;
    text-align: right;
}`,
        html: `<div class="page-header">Page 1 of 1</div>
<div class="document-title">Proforma Invoice</div>
<div class="company-section">
    <div class="company-left">
        <img src="{{company.getLogoPathForPdf()}}" alt="Company Logo" class="company-logo">
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.tagline}}</div>
        <div class="company-details">
            <p><strong>{{company.legal_name}}</strong></p>
            <p>{{company.address}}</p>
            <p>{{company.city}}</p>
            <p>VAT Number {{company.vat_number}}</p>
            <p>Telephone {{company.phone}}</p>
            <p>Fax {{company.fax}}</p>
        </div>
    </div>
    <div class="company-right">
        <div class="banking-details">
            <h4>Banking Details</h4>
            <p><strong>{{company.bank_account_name}}</strong></p>
            <p>Bank Name: {{company.bank_name}}</p>
            <p>Acc No: {{company.bank_account_number}}</p>
            <p>Sort Code: {{company.bank_sort_code}}</p>
        </div>
    </div>
</div>
<div class="customer-section">
    <div class="customer-left">
        <div class="customer-info">
            <h4>To:</h4>
            <p><strong>{{quote.customer.account_code}}</strong></p>
            <p><strong>{{quote.customer.name}}</strong></p>
            <p>{{quote.customer.address}}</p>
            <p>{{quote.customer.city}}</p>
            <p>{{quote.customer.postal_code}}</p>
        </div>
    </div>
    <div class="customer-right">
        <div class="validity-period">
            <label>Valid Until</label>
            <div style="border: 1px solid #000; height: 20px; margin-top: 5px; padding: 2px;">{{quote.expiry_date}}</div>
        </div>
    </div>
</div>
<div class="separator-line"></div>
<div class="quote-details">
    <div class="quote-detail">
        <span class="quote-detail-label">Account</span>
        <span class="quote-detail-value">{{quote.customer.account_code}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Date</span>
        <span class="quote-detail-value">{{quote.created_at}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Order No</span>
        <span class="quote-detail-value">{{quote.order_number}}</span>
    </div>
    <div class="quote-detail">
        <span class="quote-detail-label">Proforma Invoice No</span>
        <span class="quote-detail-value">{{quote.quote_number}}</span>
    </div>
</div>
<table class="line-items-table">
    <thead>
        <tr>
            <th>Item Description</th>
            <th class="text-right">QTY</th>
            <th class="text-right">Price (Ex)</th>
            <th class="text-right">Disc %</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total (Excl)</th>
        </tr>
    </thead>
    <tbody><!-- {{#each quote.lineItems}} --><tr>
            <td>{{this.description_with_code}}</td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R{{this.unit_price}}</td>
            <td class="text-right">{{this.discount_percentage}}%</td>
            <td class="text-right">R{{this.tax}}</td>
            <td class="text-right">R{{this.total}}</td>
        </tr><!-- {{/each}} --></tbody>
</table>
<div class="totals-section">
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{quote.subtotal}}</span>
    </div>
    <div class="total-row">
        <span>Tax:</span>
        <span>R{{quote.tax_amount}}</span>
    </div>
    <div class="total-row">
        <span>Total (Excl):</span>
        <span>R{{quote.total}}</span>
    </div>
    <div class="total-row">
        <span>Discount:</span>
        <span>R{{quote.discount_amount}}</span>
    </div>
    <div class="total-row">
        <span>Rounding:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row">
        <span>Less: Excess:</span>
        <span>R0.00</span>
    </div>
    <div class="total-row final">
        <span>Total (Excl):</span>
        <span>R{{quote.total}}</span>
    </div>
</div>
<div class="terms-section">
    <h4>Description</h4>
    <p>{{quote.description}}</p>
    <h4>Notes</h4>
    <p>{{quote.notes}}</p>
    <h4>Terms & Conditions</h4>
    <p>This is a proforma invoice and does not constitute a request for payment.</p>
    <p>Prices are subject to change without notice.</p>
    <p>{{quote.terms_conditions}}</p>
</div>
<div class="signature-section">
    <div class="signature-row">
        <div class="signature-item">
            <div>Received by</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Date</div>
            <div class="signature-line"></div>
        </div>
        <div class="signature-item">
            <div>Signature</div>
            <div class="signature-line"></div>
        </div>
    </div>
</div>
<div class="footer">
    <div class="footer-left">
        {{{company.pdf_footer_html}}}
    </div>
    <div class="footer-right">
        {{date}}
    </div>
</div>`,
    },
    'purchase-order': {
        css: `@page {
    margin-bottom: 70px;
}
body {
    font-family: Arial, sans-serif;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 20px;
    font-size: 12px;
    background: white;
}
.page-header {
    text-align: right;
    font-size: 10px;
    color: #666;
    margin-bottom: 10px;
}
.document-title {
    text-align: center;
    font-size: 28px;
    font-weight: bold;
    color: #000;
    margin: 20px 0;
    text-transform: uppercase;
}
.company-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.company-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.company-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.company-logo {
    max-width: 220px;
    max-height: 110px;
    margin-bottom: 10px;
}
.company-name {
    font-size: 16px;
    font-weight: bold;
    color: #000;
    margin-bottom: 5px;
}
.company-tagline {
    font-size: 11px;
    color: #666;
    margin-bottom: 15px;
}
.company-details {
    font-size: 11px;
    line-height: 1.3;
}
.company-details p {
    margin: 2px 0;
}
.supplier-section {
    display: table;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #000;
    padding: 15px;
}
.supplier-left {
    display: table-cell;
    width: 60%;
    vertical-align: top;
}
.supplier-right {
    display: table-cell;
    width: 40%;
    vertical-align: top;
    text-align: left;
}
.supplier-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.supplier-info p {
    margin: 2px 0;
    font-size: 11px;
}
.po-info {
    text-align: left;
    font-size: 11px;
}
.po-info h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.po-info p {
    margin: 2px 0;
}
.line-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.line-items-table th,
.line-items-table td {
    border-bottom: 1px solid #000;
    padding: 6px;
    font-size: 11px;
}
.line-items-table th {
    font-weight: bold;
    text-align: left;
    border-bottom: 2px solid #000;
}
.line-items-table td {
    text-align: left;
}
.line-items-table .text-right {
    text-align: right;
}
.totals-section {
    float: right;
    width: 300px;
    margin-top: 20px;
}
.total-row {
    display: table;
    width: 100%;
    margin-bottom: 5px;
}
.total-row span {
    display: table-cell;
    font-size: 11px;
    padding: 2px 0;
}
.total-row span:first-child {
    text-align: left;
}
.total-row span:last-child {
    text-align: right;
}
.total-row.final {
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid #000;
    padding-top: 8px;
    margin-top: 8px;
}
.terms-section {
    clear: both;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #000;
    font-size: 11px;
    text-align: left;
}
.terms-section h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: bold;
    color: #000;
}
.terms-section p {
    margin: 0 0 8px 0;
    font-size: 11px;
    line-height: 1.4;
}
.footer {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    font-size: 9px;
    color: #666;
    background: #fff;
    display: table;
    width: 100%;
}
.footer-left {
    display: table-cell;
    text-align: left;
}
.footer-right {
    display: table-cell;
    text-align: right;
}`,
        html: `<div class="page-header">Page 1 of 1</div>
<div class="document-title">Purchase Order</div>
<div class="company-section">
    <div class="company-left">
        <img src="{{company.logo_path_for_pdf}}" alt="Company Logo" class="company-logo">
        <div class="company-name">{{company.name}}</div>
        <div class="company-tagline">{{company.description}}</div>
        <div class="company-details">
            <p><strong>{{company.name}}</strong></p>
            <p>{{company.address}}</p>
            <p>{{company.city}}</p>
            <p>VAT Number {{company.vat_number}}</p>
            <p>Telephone {{company.phone}}</p>
            <p>Email {{company.email}}</p>
        </div>
    </div>
    <div class="company-right">
        <div class="po-info">
            <h4>Purchase Order Details</h4>
            <p><strong>PO Number:</strong> {{purchaseOrder.po_number}}</p>
            <p><strong>Order Date:</strong> {{purchaseOrder.order_date}}</p>
            <p><strong>Expected Delivery:</strong> {{purchaseOrder.expected_delivery_date}}</p>
            <p><strong>Status:</strong> {{purchaseOrder.status}}</p>
        </div>
    </div>
</div>
<div class="supplier-section">
    <div class="supplier-left">
        <div class="supplier-info">
            <h4>Supplier:</h4>
            <p><strong>{{purchaseOrder.supplier.name}}</strong></p>
            <p>{{purchaseOrder.supplier.address}}</p>
            <p>{{purchaseOrder.supplier.city}}</p>
            <p>{{purchaseOrder.supplier.postal_code}}</p>
            <p>Phone: {{purchaseOrder.supplier.phone}}</p>
            <p>Email: {{purchaseOrder.supplier.email}}</p>
        </div>
    </div>
    <div class="supplier-right">
        <div class="po-info">
            <h4>Delivery Information</h4>
            <p><strong>Expected:</strong> {{purchaseOrder.expected_delivery_date}}</p>
            <p><strong>Received:</strong> {{purchaseOrder.received_date}}</p>
        </div>
    </div>
</div>
<table class="line-items-table">
    <thead>
        <tr>
            <th>Item Code</th>
            <th>Item Description</th>
            <th class="text-right">QTY</th>
            <th class="text-right">Price (Ex)</th>
            <th class="text-right">Tax</th>
            <th class="text-right">Total (Excl)</th>
        </tr>
    </thead>
    <tbody><!-- {{#each purchaseOrder.items}} --><tr>
            <td>{{this.product.sku}}</td>
            <td>{{this.description_with_code}}</td>
            <td class="text-right">{{this.quantity}}</td>
            <td class="text-right">R{{this.unit_cost}}</td>
            <td class="text-right">{{#if this.taxRate}}R {{this.tax_amount}}{{else}}—{{/if}}</td>
            <td class="text-right">R{{this.total}}</td>
        </tr><!-- {{/each}} --></tbody>
</table>
<div class="totals-section">
    <div class="total-row">
        <span>Subtotal:</span>
        <span>R {{purchaseOrder.subtotal}}</span>
    </div>
    <div class="total-row">
        <span>Tax:</span>
        <span>R {{purchaseOrder.tax_amount}}</span>
    </div>
    <div class="total-row final">
        <span>Total:</span>
        <span>R {{purchaseOrder.total}}</span>
    </div>
</div>
<div class="terms-section">
    <h4>Notes:</h4>
    <p>{{purchaseOrder.notes}}</p>
</div>
<div class="terms-section">
    <h4>Terms & Conditions:</h4>
    <p>{{purchaseOrder.terms}}</p>
</div>
<div class="footer">
    <div class="footer-left">
        Generated on {{date}}
    </div>
    <div class="footer-right">
        {{company.name}}
    </div>
</div>`,
    },
};

// Function to restore Handlebars syntax from data attributes and auto-detect loops
function restoreHandlebarsFromAttributes(html: string, module: string): string {
    // Find tr elements with data-handlebars-loop-start attribute
    // Pattern: <tr ... data-handlebars-loop-start="{{#each ...}}" ...>
    const trPattern = /<tr([^>]*)\s+data-handlebars-loop-start="\{\{#each\s+([^}]+)\}\}"([^>]*)>/g;
    let match;
    
    while ((match = trPattern.exec(html)) !== null) {
        const fullMatch = match[0];
        const beforeAttrs = match[1] || '';
        const loopPath = match[2];
        const afterAttrs = match[3] || '';
        
        // Clean attributes - remove the data-handlebars attributes
        const cleanBefore = beforeAttrs.replace(/\s+data-handlebars-loop-start="[^"]*"/g, '').replace(/\s+data-handlebars-loop-end="[^"]*"/g, '');
        const cleanAfter = afterAttrs.replace(/\s+data-handlebars-loop-start="[^"]*"/g, '').replace(/\s+data-handlebars-loop-end="[^"]*"/g, '');
        
        // Find the corresponding closing </tr> tag
        const trStartIndex = match.index;
        let trEndIndex = html.indexOf('</tr>', trStartIndex);
        if (trEndIndex === -1) continue;
        
        // Find the actual end of the tr tag (including any nested content)
        let depth = 1;
        let searchIndex = trStartIndex + fullMatch.length;
        while (depth > 0 && searchIndex < html.length) {
            const nextOpen = html.indexOf('<tr', searchIndex);
            const nextClose = html.indexOf('</tr>', searchIndex);
            
            if (nextClose === -1) break;
            if (nextOpen !== -1 && nextOpen < nextClose) {
                depth++;
                searchIndex = nextOpen + 3;
            } else {
                depth--;
                if (depth === 0) {
                    trEndIndex = nextClose + 5;
                    break;
                }
                searchIndex = nextClose + 5;
            }
        }
        
        // Replace the tr with wrapped version
        const trContent = html.substring(trStartIndex + fullMatch.length, trEndIndex);
        const newTr = `{{#each ${loopPath}}}<tr${cleanBefore}${cleanAfter}>${trContent}</tr>{{/each}}`;
        html = html.substring(0, trStartIndex) + newTr + html.substring(trEndIndex);
        
        // Reset regex lastIndex since we modified the string
        trPattern.lastIndex = 0;
    }
    
    // Also handle HTML comments as fallback
    html = html.replace(/<!--\s*\{\{#each\s+([^}]+)\}\}\s*-->/g, '{{#each $1}}');
    html = html.replace(/<!--\s*\{\{\/each\}\}\s*-->/g, '{{/each}}');
    
    // Clean up any standalone loop markers that got separated
    html = html.replace(/\{\{#each\s+([^}]+)\}\}\{\{\/each\}\}/g, '');
    
    // Auto-detect tr elements with {{this.}} that aren't wrapped in a loop
    // Determine loop path based on module
    const loopPaths: Record<string, string> = {
        invoice: 'invoice.lineItems',
        quote: 'quote.lineItems',
        jobcard: 'jobcard.lineItems',
        'proforma-invoice': 'quote.lineItems',
        'purchase-order': 'purchaseOrder.items'
    };
    const loopPath = loopPaths[module];
    
    if (loopPath) {
        // Find <tbody> elements that contain tr with {{this.}} but no {{#each}}
        html = html.replace(/<tbody([^>]*)>([\s\S]*?)<\/tbody>/g, (tbodyMatch, tbodyAttrs, tbodyContent) => {
            // Check if tbody contains tr with {{this.}} but no {{#each wrapping it
            if (tbodyContent.includes('{{this.') && !tbodyContent.match(/\{\{#each[^}]*\}\}[\s\S]*?\{\{\/each\}\}/)) {
                // Find all <tr> elements with {{this.}} that aren't wrapped
                return tbodyMatch.replace(/<tr([^>]*)>([\s\S]*?)<\/tr>/g, (trMatch: string, trAttrs: string, trContent: string) => {
                    if (trContent.includes('{{this.') && !trMatch.includes('{{#each')) {
                        // Wrap this tr with loop
                        return `{{#each ${loopPath}}}<tr${trAttrs}>${trContent}</tr>{{/each}}`;
                    }
                    return trMatch;
                });
            }
            return tbodyMatch;
        });
    }
    
    return html;
}

function importDefaultTemplate() {
    if (!editor) return;
    
    const template = defaultTemplates[props.module];
    if (!template) {
        alert(`No default template available for module: ${props.module}`);
        return;
    }
    
    if (confirm('This will replace your current template. Are you sure?')) {
        // Set HTML content first
        editor.setComponents(template.html);
        
        // Wait a bit for the frame to be ready, then inject CSS
        setTimeout(() => {
            replaceFrameCustomStyle(template.css);
            editor.refresh();
        }, 100);
        
        // Emit updates (restore Handlebars from data attributes)
        let html = editor.getHtml();
        html = restoreHandlebarsFromAttributes(html, props.module);
        emit('update:modelValue', html);
        emit('update:cssStyles', template.css);
    }
}

watch(() => props.module, (newModule) => {
    availableVariables.value = moduleVariables[newModule] || [];
    if (editor) {
        // Update variable blocks in editor
        updateVariableBlocks();
    }
});

// Initialize GrapeJS editor
onMounted(async () => {
    await nextTick();
    
    if (!editorContainer.value) return;

    editor = grapesjs.init({
        container: editorContainer.value,
        height: '100%',
        width: '100%',
        plugins: [gjsPresetWebpage],
        pluginsOpts: {
            [gjsPresetWebpage as unknown as string]: {
                modalImportTitle: 'Import Template',
                modalImportLabel: '<div style="margin-bottom: 10px; font-size: 13px;">Paste here your HTML/CSS and click Import</div>',
                modalImportContent: (editor: any) => {
                    return editor.getHtml() + '<style>' + editor.getCss() + '</style>';
                },
            },
        } as any,
        storageManager: {
            type: 'local',
            autosave: false,
        },
        deviceManager: {
            devices: [
                {
                    name: 'Desktop',
                    width: '',
                },
            ],
        },
        blockManager: {
            appendTo: '#blocks-panel',
        },
        layerManager: {
            appendTo: '#layers-panel',
        },
        styleManager: {
            appendTo: `#${STYLES_PANEL_ID}`,
            sectors: [
                {
                    name: 'Typography',
                    open: true,
                    buildProps: ['font-family', 'font-size', 'font-weight', 'letter-spacing', 'color', 'line-height', 'text-align', 'text-decoration', 'text-shadow'],
                    properties: [
                        {
                            name: 'Text Align',
                            property: 'text-align',
                            type: 'radio',
                            defaults: 'left',
                            options: [
                                { value: 'left', name: 'Left' },
                                { value: 'center', name: 'Center' },
                                { value: 'right', name: 'Right' },
                                { value: 'justify', name: 'Justify' },
                            ],
                        },
                    ],
                },
                {
                    name: 'Dimension',
                    open: false,
                    buildProps: ['width', 'min-height', 'padding'],
                    properties: [
                        {
                            type: 'integer',
                            name: 'The width',
                            property: 'width',
                            units: ['px', '%'],
                            defaults: 'auto',
                            min: 0,
                        },
                    ],
                },
                {
                    name: 'Extra',
                    open: false,
                    buildProps: ['background-color', 'box-shadow', 'custom-prop'],
                    properties: [
                        {
                            id: 'custom-prop',
                            name: 'Custom Label',
                            property: 'font-size',
                            type: 'select',
                            defaults: '32px',
                            options: [
                                { value: '12px', name: 'Tiny' },
                                { value: '18px', name: 'Medium' },
                                { value: '32px', name: 'Big' },
                            ],
                        },
                    ],
                },
                {
                    name: 'Border',
                    open: false,
                    buildProps: ['border', 'border-radius'],
                    properties: [
                        {
                            name: 'Border Width',
                            property: 'border-width',
                            type: 'integer',
                            units: ['px'],
                            defaults: '0',
                            min: 0,
                        },
                        {
                            name: 'Border Style',
                            property: 'border-style',
                            type: 'select',
                            defaults: 'solid',
                            options: [
                                { value: 'none', name: 'None' },
                                { value: 'solid', name: 'Solid' },
                                { value: 'dashed', name: 'Dashed' },
                                { value: 'dotted', name: 'Dotted' },
                                { value: 'double', name: 'Double' },
                            ],
                        },
                        {
                            name: 'Border Color',
                            property: 'border-color',
                            type: 'color',
                        },
                        {
                            name: 'Border Radius',
                            property: 'border-radius',
                            type: 'integer',
                            units: ['px', '%'],
                            defaults: '0',
                            min: 0,
                        },
                    ],
                },
            ] as any,
        },
        panels: {
            defaults: [
                {
                    id: 'layers',
                    el: '#layers-panel',
                    resizable: false, // Disable resizing to prevent overlap
                },
                {
                    id: 'styles',
                    el: `#${STYLES_PANEL_ID}`,
                    resizable: false, // Disable resizing to prevent overlap
                },
                {
                    id: 'traits',
                    el: '#traits-panel',
                    resizable: false,
                },
            ],
        },
        traitManager: {
            appendTo: '#traits-panel',
        },
        assetManager: {
            upload: props.imageUploadUrl,
            uploadName: 'image',
            multiUpload: false,
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
            },
        } as any,
    });

    // Configure image component traits after editor initialization
    // Wait for preset to fully initialize, then override
    editor.on('load', () => {
        setTimeout(() => {
            const domComponents = editor.DomComponents;
            const traitManager = editor.TraitManager;
            
            // Register custom button trait type if not exists
            const buttonTraitType = traitManager.getType('button');
            if (!buttonTraitType) {
                traitManager.addType('button', {
                    createInput({ trait }: any) {
                        const el = document.createElement('button');
                        el.type = 'button';
                        el.textContent = trait.get('text') || trait.get('label') || 'Click';
                        el.className = 'gjs-trt-btn';
                        el.onclick = () => {
                            const command = trait.get('command');
                            if (command) {
                                command(editor, trait);
                            }
                        };
                        return el;
                    },
                });
            }
            
            // Extend image component with custom traits - override defaults
            domComponents.addType('image', {
                extend: 'image',
                model: {
                    defaults: {
                        // Remove default traits and add our custom ones
                        traits: [
                            {
                                type: 'button',
                                name: 'asset-manager',
                                label: 'Upload Image',
                                text: 'Select Image',
                                command: (editor: any) => {
                                    editor.AssetManager.open({
                                        select: (asset: any) => {
                                            const selected = editor.getSelected();
                                            if (selected && selected.get('type') === 'image') {
                                                const assetSrc = asset.getSrc();
                                                
                                                // Update the src trait directly
                                                const srcTrait = selected.getTrait('src');
                                                if (srcTrait) {
                                                    srcTrait.set('value', assetSrc);
                                                }
                                                
                                                // Set attributes using setAttributes (not addAttributes)
                                                selected.setAttributes({ 
                                                    src: assetSrc,
                                                    'data-uploaded-image': 'true'
                                                });
                                                
                                                editor.AssetManager.close();
                                                
                                                // Also update the DOM element in the frame immediately
                                                setTimeout(() => {
                                                    markFrameImageAsUploaded(assetSrc);
                                                }, 100);
                                            }
                                        },
                                    });
                                },
                            },
                            {
                                type: 'text',
                                name: 'src',
                                label: 'Image Source',
                                placeholder: 'Enter URL or {{company.getLogoPathForPdf()}}',
                                changeProp: 1,
                            },
                            {
                                type: 'text',
                                name: 'alt',
                                label: 'Alt Text',
                                placeholder: 'Image description',
                            },
                        ],
                    },
                },
            } as any);
        }, 300);
    });
    
    // Listen for trait changes on image components
    editor.on('trait:update', (trait: any, component: any) => {
        if (component && component.get('type') === 'image' && trait.get('name') === 'src') {
            const srcValue = trait.get('value') || '';
            // If src is a regular URL (not Handlebars), mark as uploaded image
            if (srcValue && 
                !srcValue.includes('{{') && 
                (srcValue.startsWith('http://') || 
                 srcValue.startsWith('https://') || 
                 srcValue.startsWith('/storage/') ||
                 srcValue.startsWith('/'))) {
                component.setAttributes({ 'data-uploaded-image': 'true' });
                
                // Also update DOM element
                setTimeout(() => {
                    markFrameImageAsUploaded(srcValue);
                }, 50);
            }
        }
    });
    
    // Also add traits dynamically when image is selected (as fallback)
    editor.on('component:selected', (component: any) => {
        if (component && component.get('type') === 'image') {
            // Force update traits
            setTimeout(() => {
                const traits = component.get('traits');
                const srcTrait = component.getTrait('src');
                
                // Check if we have the right traits
                const hasSrcTrait = srcTrait && (srcTrait.get('label') === 'Image Source' || srcTrait.get('name') === 'src');
                
                // If src trait doesn't exist or is wrong, replace all traits
                if (!hasSrcTrait || !traits || traits.length < 2) {
                    // Remove all existing traits first
                    const existingTraits = component.get('traits') || [];
                    existingTraits.forEach((trait: any) => {
                        try {
                            component.removeTrait(trait.id || trait.name || trait.get('name'));
                        } catch {
                            // Ignore errors
                        }
                    });
                    
                    // Add our custom traits
                    component.addTrait({
                        type: 'button',
                        name: 'asset-manager',
                        label: 'Upload Image',
                        text: 'Select Image',
                        command: (editor: any) => {
                            editor.AssetManager.open({
                                select: (asset: any) => {
                                    const selected = editor.getSelected();
                                    if (selected && selected.get('type') === 'image') {
                                        const assetSrc = asset.getSrc();
                                        
                                        // Update the src trait directly
                                        const srcTrait = selected.getTrait('src');
                                        if (srcTrait) {
                                            srcTrait.set('value', assetSrc);
                                        }
                                        
                                        // Set attributes using setAttributes (not addAttributes)
                                        selected.setAttributes({ 
                                            src: assetSrc,
                                            'data-uploaded-image': 'true'
                                        });
                                        
                                        editor.AssetManager.close();
                                        
                                        // Also update the DOM element in the frame immediately
                                        setTimeout(() => {
                                            markFrameImageAsUploaded(assetSrc);
                                        }, 100);
                                    }
                                },
                            });
                        },
                    });
                    
                    component.addTrait({
                        type: 'text',
                        name: 'src',
                        label: 'Image Source',
                        placeholder: 'Enter URL or {{company.getLogoPathForPdf()}}',
                        changeProp: 1,
                    });
                    
                    component.addTrait({
                        type: 'text',
                        name: 'alt',
                        label: 'Alt Text',
                        placeholder: 'Image description',
                    });
                    
                    // Force trait manager to update by triggering component update
                    editor.trigger('component:update', component);
                    editor.select(component);
                }
            }, 150);
        }
    });

    // Add variable blocks
    updateVariableBlocks();

    // Handle images with Handlebars syntax - prevent 404 errors
    setTimeout(() => {
        const canvas = editor.Canvas;
        const frame = canvas.getFrameEl();
        
        if (frame) {
            const frameDoc = frame.contentDocument || (frame as any).contentWindow?.document;
            if (frameDoc) {
                // Function to replace Handlebars image src with placeholder
                // Only replace images that have Handlebars syntax, preserve regular URLs
                const replaceHandlebarsImages = () => {
                    const images = frameDoc.querySelectorAll('img');
                    images.forEach((img: HTMLImageElement) => {
                        // Skip uploaded images (marked with data-uploaded-image)
                        if (img.hasAttribute('data-uploaded-image')) {
                            return;
                        }
                        
                        const src = img.getAttribute('src') || img.src;
                        
                        // Skip if src is a regular URL (http/https/data URI that's not our placeholder)
                        // Regular URLs should not be replaced
                        if (src && 
                            !src.includes('{{') && 
                            (src.startsWith('http://') || 
                             src.startsWith('https://') || 
                             src.startsWith('/storage/') ||
                             src.startsWith('/') ||
                             (src.startsWith('data:image') && !src.includes('PHN2ZyB3aWR0aD0iMTAw')))) {
                            // This is a regular uploaded image, mark it and skip
                            img.setAttribute('data-uploaded-image', 'true');
                            return;
                        }
                        
                        // Only process if src contains Handlebars syntax and hasn't been processed yet
                        if (src && src.includes('{{') && !img.hasAttribute('data-handlebars-src')) {
                            // Replace with a data URI placeholder
                            img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2U1ZTdlYiIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM5Y2EzYWYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5Mb2dvPC90ZXh0Pjwvc3ZnPg==';
                            // Store original src in data attribute
                            img.setAttribute('data-handlebars-src', src);
                        }
                    });
                };
                
                // Run immediately and on content changes
                replaceHandlebarsImages();
                
                // Watch for new images being added
                const observer = new MutationObserver(() => {
                    replaceHandlebarsImages();
                });
                
                observer.observe(frameDoc.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['src'],
                });
                
                // Also listen to editor component updates
                editor.on('component:update', () => {
                    setTimeout(replaceHandlebarsImages, 100);
                });
            }
        }
    }, 500);

    // Load initial content
    if (props.modelValue) {
        try {
            // Set components first
            editor.setComponents(props.modelValue);
            
            // After loading, ensure uploaded images are preserved BEFORE replaceHandlebarsImages runs
            setTimeout(() => {
                const canvas = editor.Canvas;
                const frame = canvas.getFrameEl();
                if (frame) {
                    const frameDoc = frame.contentDocument || (frame as any).contentWindow?.document;
                    if (frameDoc) {
                        const images = frameDoc.querySelectorAll('img');
                        images.forEach((img: HTMLImageElement) => {
                            const src = img.getAttribute('src') || '';
                            // If image has a regular URL (not placeholder, not Handlebars), mark it as uploaded
                            if (src && 
                                !src.includes('{{') && 
                                !src.startsWith('data:image/svg+xml') &&
                                !img.hasAttribute('data-handlebars-src') &&
                                !img.hasAttribute('data-uploaded-image')) {
                                // This is a regular uploaded image, mark it immediately
                                img.setAttribute('data-uploaded-image', 'true');
                                
                                // Also update the component attributes
                                const component = editor.getSelected();
                                if (component && component.get('type') === 'image') {
                                    component.addAttributes({ 'data-uploaded-image': 'true' });
                                }
                            }
                        });
                    }
                }
            }, 100);
        } catch (e) {
            console.error('Error loading template:', e);
        }
    }

    // Listen for changes
    editor.on('update', () => {
        let html = editor.getHtml();
        let css = editor.getCss();
        
        // Restore Handlebars syntax from data attributes and comments
        html = restoreHandlebarsFromAttributes(html, props.module);
        
        // Restore Handlebars image src from data attributes
        html = html.replace(/<img([^>]*)\s+data-handlebars-src="([^"]+)"([^>]*)>/g, (match: string, before: string, handlebarsSrc: string, after: string) => {
            // Remove data-handlebars-src attribute and restore original src
            const cleanAttrs = (before + ' ' + after).replace(/\s+data-handlebars-src="[^"]*"/g, '').trim();
            return `<img${cleanAttrs ? ' ' + cleanAttrs : ''} src="${handlebarsSrc}">`;
        });
        
        // Ensure uploaded images are marked in the saved HTML
        // Check if images have regular URLs (not Handlebars) and mark them
        html = html.replace(/<img([^>]*)\s+src="([^"]+)"([^>]*)>/g, (match: string, before: string, src: string, after: string) => {
            // If src is a regular URL (not Handlebars, not placeholder), ensure data-uploaded-image is present
            if (src && 
                !src.includes('{{') && 
                !src.startsWith('data:image/svg+xml') &&
                (src.startsWith('http://') || 
                 src.startsWith('https://') || 
                 src.startsWith('/storage/') ||
                 src.startsWith('/'))) {
                // Check if data-uploaded-image is already present
                if (!before.includes('data-uploaded-image') && !after.includes('data-uploaded-image')) {
                    // Add data-uploaded-image attribute
                    return `<img${before} data-uploaded-image="true" src="${src}"${after}>`;
                }
            }
            return match;
        });
        
        // Also get CSS from the iframe if it exists
        try {
            const customStyle = getEditorFrameDocument()?.getElementById(CUSTOM_STYLE_ID);
            if (customStyle && customStyle.textContent) {
                // Merge GrapeJS CSS with custom injected CSS
                const customCss = customStyle.textContent.trim();
                if (customCss && !css.includes(customCss)) {
                    css = css ? `${css}\n\n${customCss}` : customCss;
                }
            }
        } catch (e) {
            console.warn('Could not extract CSS from iframe:', e);
        }

        emit('update:modelValue', html);
        emit('update:cssStyles', css);
    });
    
    // Load CSS if provided - inject into frame
    if (props.cssStyles) {
        setTimeout(() => {
            replaceFrameCustomStyle(props.cssStyles);
        }, 100);
    }

    // Add custom section block
    editor.BlockManager.add('section', {
        label: 'Section',
        category: 'Layout',
        content: {
            type: 'section',
            classes: ['section-container'],
            style: {
                padding: '20px',
                margin: '10px 0',
                border: '1px solid #ddd',
                'border-radius': '4px',
            },
        },
    });

    // Add image block to Basic category
    editor.BlockManager.add('image', {
        label: 'Image',
        category: 'Basic',
        media: '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>',
        content: {
            type: 'image',
            src: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2U1ZTdlYiIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTIiIGZpbGw9IiM5Y2EzYWYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5JbWFnZTwvdGV4dD48L3N2Zz4=',
            style: {
                width: '100%',
                height: 'auto',
            },
        },
    });

    // Add heading blocks to Basic category
    editor.BlockManager.add('heading1', {
        label: 'Heading 1',
        category: 'Basic',
        content: '<h1 style="font-size: 2em; font-weight: bold; margin: 0.5em 0;">Insert heading here</h1>',
    });

    editor.BlockManager.add('heading2', {
        label: 'Heading 2',
        category: 'Basic',
        content: '<h2 style="font-size: 1.5em; font-weight: bold; margin: 0.5em 0;">Insert heading here</h2>',
    });

    editor.BlockManager.add('heading3', {
        label: 'Heading 3',
        category: 'Basic',
        content: '<h3 style="font-size: 1.25em; font-weight: bold; margin: 0.5em 0;">Insert heading here</h3>',
    });

    // Add text block to Basic category
    editor.BlockManager.add('text-block', {
        label: 'Text Block',
        category: 'Basic',
        content: '<p style="margin: 0.5em 0;">Insert text here</p>',
    });

    // Add divider/separator to Basic category
    editor.BlockManager.add('divider', {
        label: 'Divider',
        category: 'Basic',
        content: '<hr style="border: none; border-top: 1px solid #ddd; margin: 1em 0;">',
    });

    // Add link to Basic category
    editor.BlockManager.add('link', {
        label: 'Link',
        category: 'Basic',
        content: '<a href="#" style="color: #3b82f6; text-decoration: underline;">Insert link text here</a>',
    });

    // Add div block to Basic category
    editor.BlockManager.add('div-container', {
        label: 'Div Container',
        category: 'Basic',
        activate: true,
        content: {
            tagName: 'div',
            type: 'default',
            droppable: true,
            editable: true,
            style: {
                padding: '10px',
                margin: '5px 0',
                'min-height': '50px',
                display: 'block',
            },
        },
    } as any);

    // Add table block to Basic category
    editor.BlockManager.add('table', {
        label: 'Table',
        category: 'Basic',
        content: `<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f3f4f6;">Header 1</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f3f4f6;">Header 2</th>
                    <th style="border: 1px solid #ddd; padding: 8px; text-align: left; background-color: #f3f4f6;">Header 3</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 1</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 2</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 3</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 4</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 5</td>
                    <td style="border: 1px solid #ddd; padding: 8px;">Cell 6</td>
                </tr>
            </tbody>
        </table>`,
    });

    // Add variable insertion functionality
    setupVariableInsertion();

    // Setup collapsible classes panel
    setupCollapsibleClassesPanel();
});

function setupCollapsibleClassesPanel() {
    if (!editor) return;

    // Wait for the style manager to be rendered
    setTimeout(() => {
        const stylesPanel = getStylesPanelElement();
        if (!stylesPanel) return;

        // Find the classes panel container
        const classesPanel = stylesPanel.querySelector(CLASSES_PANEL_SELECTOR) as HTMLElement;
        if (!classesPanel) return;

        // Create toggle button
        const toggleButton = document.createElement('button');
        toggleButton.className = 'classes-panel-toggle';
        toggleButton.innerHTML = `
            <span class="toggle-icon">▼</span>
            <span class="toggle-label">Classes & Selected</span>
        `;
        toggleButton.setAttribute('type', 'button');
        
        // Insert toggle button before the classes panel
        const classesContainer = classesPanel.parentElement;
        if (classesContainer) {
            classesContainer.insertBefore(toggleButton, classesPanel);
            
            // Toggle function
            toggleButton.addEventListener('click', () => {
                isClassesPanelCollapsed.value = !isClassesPanelCollapsed.value;
                const isCollapsed = isClassesPanelCollapsed.value;
                
                if (isCollapsed) {
                    classesPanel.style.display = 'none';
                    const icon = toggleButton.querySelector(TOGGLE_ICON_SELECTOR);
                    if (icon) icon.textContent = '▶';
                    toggleButton.classList.add('collapsed');
                } else {
                    classesPanel.style.display = '';
                    const icon = toggleButton.querySelector(TOGGLE_ICON_SELECTOR);
                    if (icon) icon.textContent = '▼';
                    toggleButton.classList.remove('collapsed');
                }
            });
        }
    }, 500);
}

function updateVariableBlocks() {
    if (!editor) return;

    // Clear existing variable blocks
    const blocks = editor.BlockManager.getAll();
    blocks.forEach((block: any) => {
        if (block.get('category') === 'Variables') {
            editor.BlockManager.remove(block.id);
        }
    });

    // Add variable blocks grouped by category
    Object.entries(groupedVariables.value).forEach(([category, variables]) => {
        variables.forEach((variable) => {
            editor.BlockManager.add(`var-${category}-${variable.value}`, {
                label: variable.label,
                category: 'Variables',
                content: `<div class="template-variable-wrapper" style="display: block; width: 100%;"><span class="template-variable" data-variable="${variable.value}">${variable.value}</span></div>`,
                attributes: {
                    class: 'gjs-block-variable',
                },
            });
        });
    });
}

function setupVariableInsertion() {
    // Variable insertion is handled by GrapeJS block manager
    // Variables are added as blocks that can be dragged into the canvas
}

onUnmounted(() => {
    if (editor) {
        editor.destroy();
    }
});

watch(() => props.modelValue, (newValue) => {
    if (editor && newValue !== editor.getHtml()) {
        try {
            editor.setComponents(newValue);
            
            // After loading, mark uploaded images immediately
            setTimeout(() => {
                const canvas = editor.Canvas;
                const frame = canvas.getFrameEl();
                if (frame) {
                    const frameDoc = frame.contentDocument || (frame as any).contentWindow?.document;
                    if (frameDoc) {
                        const images = frameDoc.querySelectorAll('img');
                        images.forEach((img: HTMLImageElement) => {
                            const src = img.getAttribute('src') || '';
                            // If image has a regular URL and data-uploaded-image attribute, preserve it
                            if (src && 
                                !src.includes('{{') && 
                                !src.startsWith('data:image/svg+xml') &&
                                (img.hasAttribute('data-uploaded-image') || 
                                 src.startsWith('http://') || 
                                 src.startsWith('https://') || 
                                 src.startsWith('/storage/') ||
                                 src.startsWith('/'))) {
                                img.setAttribute('data-uploaded-image', 'true');
                            }
                        });
                    }
                }
            }, 100);
        } catch (e) {
            console.error('Error updating template:', e);
        }
    }
});
</script>

<template>
    <div class="pdf-template-editor">
        <div class="editor-layout">
            <!-- Left Sidebar: Blocks -->
            <div class="editor-sidebar editor-sidebar-left">
                <div class="panel-section panel-section-button">
                    <button
                        @click="importDefaultTemplate"
                        class="w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 mb-3"
                        :disabled="disabled"
                        type="button"
                    >
                        Import Default Template
                    </button>
                    <p v-pre class="text-xs text-gray-600 leading-snug mb-3">
                        Placeholders: use <code class="bg-gray-200 px-0.5 rounded">{{ customer.name }}</code> for plain text (HTML is escaped).
                        Use <code class="bg-gray-200 px-0.5 rounded">{{{ invoice.notes }}}</code> only when a field must render stored HTML (e.g. terms).
                    </p>
                </div>
                <div id="blocks-panel" class="panel-section panel-section-scrollable">
                    <h3 class="panel-title">Blocks</h3>
                </div>
            </div>

            <!-- Center: Canvas -->
            <div class="editor-canvas-container">
                <div id="editor-container" ref="editorContainer"></div>
            </div>

            <!-- Right Sidebar: Layers and Styles -->
            <div class="editor-sidebar editor-sidebar-right">
                <div id="layers-panel" class="panel-section panel-section-scrollable">
                    <h3 class="panel-title">Layers</h3>
                </div>
                <div id="traits-panel" class="panel-section panel-section-scrollable">
                    <div class="panel-header">Settings</div>
                </div>
                <div id="styles-panel" class="panel-section panel-section-scrollable">
                    <h3 class="panel-title">Styles</h3>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

.pdf-template-editor {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    overflow: hidden;
    background: #fff;
    width: 100%;
}

.editor-layout {
    display: flex;
    height: 800px;
    width: 100%;
    overflow: hidden; /* Prevent overflow */
}

.editor-sidebar {
    width: 250px;
    min-width: 250px;
    max-width: 250px;
    background: #f9fafb;
    border-right: 1px solid #e5e7eb;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    flex-shrink: 0; /* Prevent sidebar from shrinking */
    position: relative;
    z-index: 1;
}

.editor-sidebar-right {
    border-right: none;
    border-left: 1px solid #e5e7eb;
}

.panel-section {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    position: relative;
}

.panel-section-button {
    flex-shrink: 0;
}

.panel-section-scrollable {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.panel-section:last-child {
    border-bottom: none;
}

.panel-title {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin: 0 0 12px 0;
}

/* Ensure GrapeJS panels are contained within sidebar - only constrain position, preserve styling */
.panel-section :deep(.gjs-pn-panel) {
    position: relative !important;
    left: auto !important;
    right: auto !important;
    top: auto !important;
    bottom: auto !important;
    width: 100% !important;
    max-width: 100% !important;
}

.panel-section :deep(.gjs-pn-views-container) {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    max-height: 100% !important;
    height: auto;
}

.editor-canvas-container {
    flex: 1;
    overflow: auto;
    background: #e5e7eb;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 40px 20px;
    min-width: 0; /* Allow flex item to shrink */
    position: relative;
    z-index: 0;
}

#editor-container {
    width: 100%;
    min-height: 1123px; /* A4 height at 96 DPI */
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    margin: 0 auto;
    position: relative;
}

/* A4 page styling */
:deep(.gjs-cv-canvas) {
    background: #e5e7eb !important;
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
}

:deep(.gjs-frame) {
    width: 794px !important;
    max-width: 794px !important;
    min-height: fit-content;
    background: #fff !important;
    margin: 0 auto;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Ensure GrapeJS panels are properly contained - minimal overrides to preserve default styling */
:deep(.gjs-pn-panels) {
    visibility: visible !important;
}

/* Ensure GrapeJS toolbar and commands are visible - only override visibility, not layout */
:deep(.gjs-pn-commands) {
    visibility: visible !important;
}

:deep(.gjs-toolbar) {
    visibility: visible !important;
}

:deep(.gjs-toolbar-item) {
    visibility: visible !important;
}

:deep(.gjs-cm) {
    visibility: visible !important;
}

/* Ensure GrapeJS default panels container is visible */
:deep(.gjs-pn-panels) {
    visibility: visible !important;
}

:deep(.gjs-cv-canvas__frames) {
    background: #e5e7eb !important;
}

:deep(.gjs-cv-canvas__frames__frame) {
    background: #fff !important;
}


/* GrapeJS Custom Styles */
:deep(.gjs-block-variable) {
    background: #dbeafe;
    border-color: #3b82f6;
}

:deep(.template-variable-wrapper) {
    display: block;
    width: 100%;
}

:deep(.template-variable) {
    background: #dbeafe;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.9em;
    color: #1e40af;
    border: 1px dashed #3b82f6;
    display: inline-block;
}

/* Classes panel toggle button */
.classes-panel-toggle {
    width: 100%;
    padding: 8px 12px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-bottom: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    transition: background-color 0.2s;
}

.classes-panel-toggle:hover {
    background: #e5e7eb;
}

.classes-panel-toggle.collapsed {
    border-bottom: 1px solid #e5e7eb;
}

.toggle-icon {
    font-size: 10px;
    transition: transform 0.2s;
    display: inline-block;
    width: 12px;
    text-align: center;
}

.toggle-label {
    flex: 1;
    text-align: left;
}
</style>

