<?php

namespace App\Enums;

enum InvoiceType: string
{
    case CustomerMaster = 'customer_master';
    case Customer = 'customer';
    case Seller = 'seller';
}
