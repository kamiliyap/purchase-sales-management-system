<?php

return [
    'name' => env('PDF_COMPANY_NAME', 'PT Sinar Niaga Teknologi'),
    'business' => env('PDF_COMPANY_BUSINESS', 'Distributor Perangkat IT'),
    'simulation' => true,
    'address' => env('PDF_COMPANY_ADDRESS'),
    'email' => env('PDF_COMPANY_EMAIL'),
    'phone' => env('PDF_COMPANY_PHONE'),
    'tax_number' => env('PDF_COMPANY_NPWP'),
    // Optional PNG/JPEG path relative to public/.
    'logo' => env('PDF_COMPANY_LOGO'),
];
