export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'ppc' | string;
    permissions?: string[];
    created_at?: string;
}

export interface Customer {
    id: number;
    name: string;
    code: string;
    ports_count?: number;
    ports?: Port[];
}

export interface Port {
    id: number;
    customer_id: number;
    name: string;
    customer?: Customer;
}

export interface Carline {
    id: number;
    code: string;
    name?: string;
    description?: string;
}

export interface Assy {
    id: number;
    carline_id: number;
    assy_number: string;
    assy_code: string;
    level: string;
    pattern?: string;
    standard_sea_quantity?: number;
    standard_air_quantity?: number;
    max_quantity_sea?: number;
    max_quantity_air?: number;
    umh: number;
    is_active: boolean;
    carline?: Carline;
}

export interface ProductionWeek {
    id: number;
    customer_id?: number | null;
    year: number;
    month_number: number;
    month_name: string;
    week_no: number;
    week_start: string;
    end_date?: string;
    customer?: Customer;
}

export interface SummaryItem {
    id?: number | string;
    upload_batch_id?: number;
    customer_id?: number;
    port_id?: number;
    assy_id?: number;
    assy_number?: string;
    assy_spp_number?: string;
    customer?: string;
    order_type?: string;
    etd?: string;
    eta?: string;
    week?: string | number;
    month?: string;
    year?: number;
    qty?: number | string;
    total_qty?: number;
    line_count?: number;
    route?: string;
    port?: string;
    model?: string;
    family?: string;
    extra?: Record<string, any>;
    assy?: Assy;
    is_editable?: boolean;
    summary_id?: number | string;
}

export interface SppRecord {
    id: number;
    spp_batch_id: number;
    upload_batch_id?: number;
    customer_id: number;
    port_id?: number;
    assy_id?: number;
    type?: string;
    assy_number: string;
    level?: string;
    assy_code?: string;
    std_pack?: number;
    umh?: number;
    period: string;
    order_type: 'FIRM' | 'FORECAST';
    bal_qty: number;
    del_qty: number;
    prod_qty: number;
    total_qty: number;
    extra?: string | Record<string, any>;
    customer?: Customer;
    port?: Port;
    assy?: Assy;
}

export interface UploadBatch {
    id: number;
    customer_id?: number;
    port_id?: number;
    batch_uuid: string;
    source_file: string;
    sheet_name?: string;
    sheet_index?: number;
    record_count?: number;
    total_qty?: number;
    uploaded_by?: number;
    status: 'processing' | 'completed' | 'failed' | string;
    created_at?: string;
    customer?: Customer;
    port?: Port;
}
