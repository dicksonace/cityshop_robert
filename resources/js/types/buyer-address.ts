export type BuyerAddress = {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    phone: string;
    secondary_phone?: string | null;
    address_line: string;
    additional_details?: string | null;
    region: string;
    city: string;
    digital_address?: string | null;
    is_default: boolean;
};
