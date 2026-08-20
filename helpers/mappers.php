<?php

function userRow(array $r, bool $includePassword = false): array
{
    $out = [
        'id' => isset($r['id']) ? (int)$r['id'] : null,
        'fullName' => $r['full_name'] ?? null,
        'email' => $r['email'] ?? null,
        'mobile' => $r['mobile'] ?? null,
        'address' => $r['address'] ?? null,
        'role' => $r['role'] ?? null,
        'referenceAdminEmail' => $r['reference_admin_email'] ?? null,
        'status' => $r['status'] ?? null,
        'failedAttempts' => isset($r['failed_attempts']) ? (int)$r['failed_attempts'] : 0,
        'agencyId' => $r['agency_id'] ?? null,
        'lockTime' => isset($r['lock_time']) ? (int)$r['lock_time'] : null,
    ];
    if ($includePassword) $out['password'] = $r['password'] ?? null;
    return $out;
}

function vehicleRow(array $r): array
{
    return [
        'id' => [
            'repoYear' => $r['repo_year'] ?? null,
            'repoMonth' => $r['repo_month'] ?? null,
            'loanNumber' => $r['loan_number'] ?? null,
        ],
        'vehicleNumber' => $r['vehicle_number'] ?? null,
        'chassisNumber' => $r['chassis_number'] ?? null,
        'engineNumber' => $r['engine_number'] ?? null,
        'color' => $r['color'] ?? null,
        'manufactureName' => $r['manufacture_name'] ?? null,
        'model' => $r['model'] ?? null,
        'vehicleMake' => $r['vehicle_make'] ?? null,
        'vehicleType' => $r['vehicle_type'] ?? null,
        'ownerName' => $r['owner_name'] ?? null,
        'ownerMobile' => $r['owner_mobile'] ?? null,
        'customerAddress' => $r['customer_address'] ?? null,
        'customerArea' => $r['customer_area'] ?? null,
        'agencyId' => $r['agency_id'] ?? null,
        'agencyIdGiveByFinance' => $r['agencyid_give_by_finance'] ?? null,
        'agencyName' => $r['agency_name'] ?? null,
        'agencyManager' => $r['agency_manager'] ?? null,
        'agencyMobile' => $r['agency_mobile'] ?? null,
        'agencyMobile2' => $r['agency_mobile2'] ?? null,
        'executiveName' => $r['executive_name'] ?? null,
        'repoStatus' => $r['repo_status'] ?? null,
        'allocationDpd' => $r['allocation_dpd'] ?? null,
        'finance' => $r['finance'] ?? null,
        'branch' => $r['branch'] ?? null,
        'area' => $r['area'] ?? null,
        'areaManagerName' => $r['area_manager_name'] ?? null,
        'areaManagerMobileNo' => $r['area_manager_mobile_no'] ?? null,
        'areaManagerEmailId' => $r['area_manager_email_id'] ?? null,
        'contactName2' => $r['contact_name2'] ?? null,
        'contactName2Designation' => $r['contact_name2_designation'] ?? null,
        'contactName2MobileNo' => $r['contact_name2_mobile_no'] ?? null,
        'regionManagerName' => $r['region_manager_name'] ?? null,
        'regionManagerMobileNo' => $r['region_manager_mobile_no'] ?? null,
        'regionManagerEmailId' => $r['region_manager_email_id'] ?? null,
        'refLetter' => $r['ref_letter'] ?? null,
        'totalCharges' => isset($r['total_charges']) ? (float)$r['total_charges'] : null,
        'uploadBy' => $r['upload_by'] ?? null,
        'uploadDate' => $r['upload_date'] ?? null,
        'yardId' => isset($r['yard_id']) ? (int)$r['yard_id'] : null,
        'yard' => isset($r['yard_id']) && array_key_exists('yard_name', $r) && $r['yard_id'] !== null ? [
            'id' => (int)$r['yard_id'],
            'yardName' => $r['yard_name'] ?? null,
            'yardAddress' => $r['yard_address'] ?? null,
            'yardManagerName' => $r['yard_manager_name'] ?? null,
            'yardContactNo' => $r['yard_contact_no'] ?? null,
            'agencyId' => $r['yard_agency_id'] ?? null,
        ] : null,
    ];
}

function invoiceRow(array $r): array
{
    return [
        'id' => isset($r['id']) ? (int)$r['id'] : null,
        'invoiceNumber' => $r['invoice_number'] ?? null,
        'invoiceDate' => $r['invoice_date'] ?? null,
        'repoYear' => $r['repo_year'] ?? null,
        'repoMonth' => $r['repo_month'] ?? null,
        'invoiceBank' => $r['invoice_bank'] ?? null,
        'invoiceAddress' => $r['invoice_address'] ?? null,
        'loanNumber' => $r['loan_number'] ?? null,
        'customerName' => $r['customer_name'] ?? null,
        'vehicleNumber' => $r['vehicle_number'] ?? null,
        'vehicleType' => $r['vehicle_type'] ?? null,
        'vehicleMake' => $r['vehicle_make'] ?? null,
        'vehicleModel' => $r['vehicle_model'] ?? null,
        'engineNumber' => $r['engine_number'] ?? null,
        'chassisNumber' => $r['chassis_number'] ?? null,
        'description1' => $r['description_1'] ?? null,
        'basic1Amount' => isset($r['basic1_amount']) ? (float)$r['basic1_amount'] : null,
        'description2' => $r['description_2'] ?? null,
        'basic2Amount' => isset($r['basic2_amount']) ? (float)$r['basic2_amount'] : null,
        'cgst' => isset($r['cgst']) ? (float)$r['cgst'] : null,
        'sgst' => isset($r['sgst']) ? (float)$r['sgst'] : null,
        'igst' => isset($r['igst']) ? (float)$r['igst'] : null,
        'totalBasic' => isset($r['total_basic']) ? (float)$r['total_basic'] : null,
        'gst' => isset($r['gst']) ? (float)$r['gst'] : null,
        'invoiceTotal' => isset($r['invoice_total']) ? (float)$r['invoice_total'] : null,
        'remarks' => $r['remarks'] ?? null,
        'createdBy' => $r['created_by'] ?? null,
        'createdDate' => $r['created_date'] ?? null,
        'gstPercent' => isset($r['gst_percent']) ? (float)$r['gst_percent'] : null,
        'paymentDate' => $r['payment_date'] ?? null,
        'paymentReceived' => isset($r['payment_received']) ? (float)$r['payment_received'] : null,
        'paymentStatus' => $r['payment_status'] ?? null,
        'agencyId' => $r['agency_id'] ?? null,
    ];
}

function searchHistoryRow(array $r): array
{
    return [
        'id' => isset($r['id']) ? (int)$r['id'] : null,
        'vehicleNumber' => $r['vehicle_number'] ?? null,
        'userEmail' => $r['user_email'] ?? null,
        'userName' => $r['user_name'] ?? null,
        'searchTime' => $r['search_time'] ?? null,
        'agencyId' => $r['agency_id'] ?? null,
    ];
}

function yardRow(array $r): array
{
    return [
        'id' => isset($r['id']) ? (int)$r['id'] : null,
        'yardName' => $r['yard_name'] ?? null,
        'yardAddress' => $r['yard_address'] ?? null,
        'yardManagerName' => $r['yard_manager_name'] ?? null,
        'yardContactNo' => $r['yard_contact_no'] ?? null,
        'agencyId' => $r['agency_id'] ?? null,
    ];
}
