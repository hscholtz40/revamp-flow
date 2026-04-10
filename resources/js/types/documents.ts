export interface DocumentLineGroup {
    id?: number;
    name: string;
    sort_order: number;
}

export interface DocumentLineItem {
    id?: number;
    _uid?: string;
    product_id: string | number | null;
    line_group_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate_id?: number | null;
    account_id?: number | null;
    total?: number;
    serial_number_ids?: number[];
}
