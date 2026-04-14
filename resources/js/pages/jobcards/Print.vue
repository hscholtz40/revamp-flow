<template>
    <div class="print-container">
        <!-- Print Header -->
        <div class="print-header">
            <div class="company-info">
                <div class="company-name">{{ props.currentCompany.name }}</div>
                <div v-if="props.currentCompany.email">{{ props.currentCompany.email }}</div>
                <div v-if="props.currentCompany.phone">{{ props.currentCompany.phone }}</div>
            </div>
            
            <div class="jobcard-title">{{ props.jobcard.title }}</div>
            <div class="jobcard-number">Jobcard #{{ props.jobcard.job_number }}</div>
        </div>

        <!-- Customer and Jobcard Info -->
        <div class="info-grid">
            <div class="info-section">
                <h3>Customer Information</h3>
                <p><strong>{{ props.jobcard.customer.name }}</strong></p>
                <p v-if="props.jobcard.customer.email">{{ props.jobcard.customer.email }}</p>
                <p v-if="props.jobcard.customer.phone">{{ props.jobcard.customer.phone }}</p>
                <p v-if="props.jobcard.customer.address">{{ props.jobcard.customer.address }}</p>
            </div>
            
            <div class="info-section">
                <h3>Jobcard Details</h3>
                <p><strong>Status:</strong> {{ formatStatus(props.jobcard.status) }}</p>
                <p v-if="props.jobcard.start_date"><strong>Start Date:</strong> {{ formatDate(props.jobcard.start_date) }}</p>
                <p v-if="props.jobcard.due_date"><strong>Due Date:</strong> {{ formatDate(props.jobcard.due_date) }}</p>
                <p v-if="props.jobcard.completed_date"><strong>Completed:</strong> {{ formatDate(props.jobcard.completed_date) }}</p>
            </div>
        </div>

        <!-- Description -->
        <div v-if="props.jobcard.description" class="description-section">
            <h3>Description</h3>
            <p>{{ props.jobcard.description }}</p>
        </div>

        <!-- Line Items -->
        <div class="line-items">
            <h3>Line Items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in props.jobcard.line_items" :key="item.id">
                        <td>{{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}</td>
                        <td class="text-right">{{ item.quantity }}</td>
                        <td class="text-right">R{{ Number(item.unit_price).toFixed(2) }}</td>
                        <td class="text-right">R{{ Number(item.total).toFixed(2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>R{{ Number(props.jobcard.subtotal || 0).toFixed(2) }}</span>
                </div>
                <div v-if="props.jobcard.tax_rate > 0" class="total-row">
                    <span>Tax ({{ props.jobcard.tax_rate }}%):</span>
                    <span>R{{ Number(props.jobcard.tax_amount || 0).toFixed(2) }}</span>
                </div>
                <div class="total-row final">
                    <span>Total:</span>
                    <span>R{{ Number(props.jobcard.total || 0).toFixed(2) }}</span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div v-if="props.jobcard.notes" class="notes-section">
            <h3>Notes</h3>
            <p>{{ props.jobcard.notes }}</p>
        </div>

        <!-- Terms & Conditions -->
        <div v-if="props.jobcard.terms_conditions" class="terms-section">
            <h3>Terms & Conditions</h3>
            <p>{{ props.jobcard.terms_conditions }}</p>
        </div>

        <!-- Print Actions -->
        <div class="print-actions">
            <button @click="printJobcard" class="print-btn">Print</button>
            <button @click="goBack" class="back-btn">Back to Jobcard</button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import jobcards from '@/routes/jobcards';
import { router } from '@inertiajs/vue3';

interface Jobcard {
    id: number;
    job_number: string;
    title: string;
    description: string | null;
    status: string;
    start_date: string | null;
    due_date: string | null;
    completed_date: string | null;
    tax_rate: number;
    subtotal: number | null;
    tax_amount: number | null;
    total: number | null;
    notes: string | null;
    terms_conditions: string | null;
    customer: {
        name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    };
    line_items: Array<{
        id: number;
        description: string;
        quantity: number;
        unit_price: number;
        total: number;
        product?: { sku?: string | null; barcode?: string | null } | null;
    }>;
}

interface Company {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
}

interface Props {
    jobcard: Jobcard;
    currentCompany: Company;
}

const props = defineProps<Props>();

const formatStatus = (status: string) => {
    return status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
};

const formatDate = (date: string | null) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const printJobcard = () => {
    window.print();
};

const goBack = () => {
    router.visit(jobcards.show(props.jobcard.id).url);
};
</script>

<style scoped>
.print-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: Arial, sans-serif;
    line-height: 1.6;
    color: #333;
}

.print-header {
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.company-info {
    text-align: center;
    margin-bottom: 20px;
}

.company-name {
    font-size: 24px;
    font-weight: bold;
    color: #1f2937;
}

.jobcard-title {
    font-size: 20px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 10px;
}

.jobcard-number {
    font-size: 16px;
    color: #6b7280;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.info-section h3 {
    margin: 0 0 10px 0;
    color: #374151;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-section p {
    margin: 0 0 5px 0;
}

.description-section,
.notes-section,
.terms-section {
    margin-bottom: 30px;
}

.description-section h3,
.notes-section h3,
.terms-section h3 {
    margin: 0 0 10px 0;
    color: #374151;
    font-size: 16px;
}

.line-items {
    margin-bottom: 30px;
}

.line-items h3 {
    margin: 0 0 15px 0;
    color: #374151;
    font-size: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

th {
    background-color: #f9fafb;
    font-weight: bold;
    color: #374151;
}

.text-right {
    text-align: right;
}

.totals {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid #e5e7eb;
}

.total-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.total-row.final {
    font-weight: bold;
    font-size: 18px;
    color: #1f2937;
    border-top: 1px solid #e5e7eb;
    padding-top: 10px;
    margin-top: 10px;
}

.print-actions {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.print-btn,
.back-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
}

.print-btn {
    background-color: #3b82f6;
    color: white;
}

.print-btn:hover {
    background-color: #2563eb;
}

.back-btn {
    background-color: #6b7280;
    color: white;
}

.back-btn:hover {
    background-color: #4b5563;
}

/* Print Styles */
@media print {
    .print-actions {
        display: none;
    }
    
    .print-container {
        padding: 0;
    }
    
    body {
        margin: 0;
        padding: 0;
    }
}
</style>
