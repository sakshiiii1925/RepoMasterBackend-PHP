<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../helpers/mappers.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelService
{
    private array $headers = [
        'Repo Year',
        'Repo Month',
        'Loan Number',
        'Vehicle Number',
        'Chassis Number',
        'Engine Number',
        'Color',
        'Vehicle Model',
        'Vehicle Make',
        'Vehicle Type',
        'Manufacture Name',
        'Customer Name',
        'Customer Mobile No',
        'Customer Address',
        'Customer Area',
        'Agency ID',
        'AgencyID give by Finance',
        'Agency Name',
        'Agency Manager',
        'Agency Mobile',
        'Agency Mobile2',
        'Executive Name',
        'Finance Company',
        'Branch',
        'Area',
        'Area Manager Name',
        'Area Manager Mobile No',
        'Area Manager Email ID',
        'Contact Name2',
        'Contact Name2 Designation',
        'Contact Name2 Mobile No',
        'Region Manager Name',
        'Region Manager Mobile No',
        'Region Manager Email ID',
        'Ref Letter',
        'Total Charges',
        'Upload By',
        'Upload Date (YYYY-MM-DD)',
        'Allocation DPD',
        'Repo Status'
    ];

    public function __construct(private PDO $pdo)
    {
    }

    private function clean(?string $v): string
    {
        return strtoupper(
            str_replace(
                ['-', '/', '.'],
                '',
                trim((string) $v)
            )
        );
    }

    private function dateValue(string $v): ?string
    {
        $v = trim($v);

        if ($v === '') {
            return null;
        }

        foreach ([
            'm/d/y',
            'm/d/Y',
            'd-m-Y',
            'd/m/Y',
            'Y-m-d'
        ] as $fmt) {

            $d = DateTime::createFromFormat($fmt, $v);

            if ($d && $d->format($fmt) === $v) {
                return $d->format('Y-m-d');
            }
        }

        $ts = strtotime($v);

        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        throw new RuntimeException(
            'Invalid date format: ' . $v
        );
    }

    public function upload(array $file, string $agency): array
    {
        /*
         * Validate uploaded file
         */
        if (
            empty($file['tmp_name']) ||
            ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        ) {
            return [
                'totalRows' => 0,
                'inserted' => 0,
                'updated' => 0,
                'failed' => 1,
                'errors' => ['No file selected.']
            ];
        }

        /*
         * Only XLSX allowed
         */
        if (
            strtolower(
                pathinfo($file['name'], PATHINFO_EXTENSION)
            ) !== 'xlsx'
        ) {
            return [
                'totalRows' => 0,
                'inserted' => 0,
                'updated' => 0,
                'failed' => 1,
                'errors' => ['Only .xlsx files are allowed.']
            ];
        }

        /*
         * Load Excel
         */
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();

        /*
         * Validate Excel headers
         */
        foreach ($this->headers as $i => $expectedHeader) {

            $column = Coordinate::stringFromColumnIndex($i + 1);

            $actualHeader = trim(
                (string) $sheet
                    ->getCell($column . '1')
                    ->getFormattedValue()
            );

            if (strcasecmp($expectedHeader, $actualHeader) !== 0) {

                throw new RuntimeException(
                    "Invalid Excel format. Expected column '" .
                    $expectedHeader .
                    "' at position " .
                    ($i + 1) .
                    " but found '" .
                    $actualHeader .
                    "'"
                );
            }
        }

        /*
         * Read Excel rows
         */
        $rows = [];
        $total = 0;

        for (
            $row = 2;
            $row <= $sheet->getHighestRow();
            $row++
        ) {

            $values = [];

            for (
                $col = 1;
                $col <= count($this->headers);
                $col++
            ) {

                $column = Coordinate::stringFromColumnIndex($col);

                $cell = $sheet->getCell(
                    $column . $row
                );

                $rawValue = (string) $cell->getFormattedValue();

                /*
                 * Column 38 = Upload Date
                 * Don't remove / - .
                 */
                if ($col === 38) {

                    $values[] = trim($rawValue);

                } else {

                    $values[] = $this->clean($rawValue);
                }
            }

            /*
             * Skip completely empty row
             */
            if (implode('', $values) === '') {
                continue;
            }

            $total++;

            /*
             * Agency validation
             *
             * Excel Agency ID = column 16
             * Array index = 15
             */
            if (
                strcasecmp(
                    $agency,
                    $values[15]
                ) !== 0
            ) {

                return [
                    'totalRows' => $total,
                    'inserted' => 0,
                    'updated' => 0,
                    'failed' => $total,
                    'errors' => [
                        "Upload failed.\n" .
                        "Row $row: Excel Agency ID '" .
                        $values[15] .
                        "' does not match your Agency ID '" .
                        $agency .
                        "'."
                    ]
                ];
            }

            $rows[] = $values;
        }

        /*
         * Database transaction
         */
        $this->pdo->beginTransaction();

        $inserted = 0;
        $updated = 0;

        try {

            foreach ($rows as $v) {

                /*
                 * Convert Excel row into database fields
                 */
                $d = [

                    'repo_year' => $v[0],
                    'repo_month' => $v[1],
                    'loan_number' => $v[2],

                    'vehicle_number' => $v[3],
                    'chassis_number' => $v[4],
                    'engine_number' => $v[5],
                    'color' => $v[6],

                    'model' => $v[7],
                    'vehicle_make' => $v[8],
                    'vehicle_type' => $v[9],
                    'manufacture_name' => $v[10],

                    'owner_name' => $v[11],
                    'owner_mobile' => $v[12],

                    'customer_address' => $v[13],
                    'customer_area' => $v[14],

                    'agency_id' => $v[15],
                    'agencyid_give_by_finance' => $v[16],

                    'agency_name' => $v[17],
                    'agency_manager' => $v[18],

                    'agency_mobile' => $v[19],
                    'agency_mobile2' => $v[20],

                    'executive_name' => $v[21],

                    'finance' => $v[22],
                    'branch' => $v[23],
                    'area' => $v[24],

                    'area_manager_name' => $v[25],
                    'area_manager_mobile_no' => $v[26],
                    'area_manager_email_id' => $v[27],

                    'contact_name2' => $v[28],
                    'contact_name2_designation' => $v[29],
                    'contact_name2_mobile_no' => $v[30],

                    'region_manager_name' => $v[31],
                    'region_manager_mobile_no' => $v[32],
                    'region_manager_email_id' => $v[33],

                    'ref_letter' => $v[34],

                    'total_charges' =>
                        $v[35] === ''
                            ? null
                            : (float) $v[35],

                    'upload_by' => $v[36],

                    'upload_date' =>
                        $this->dateValue($v[37]),

                    'allocation_dpd' => $v[38],
                    'repo_status' => $v[39]
                ];

                /*
                 * Check existing vehicle
                 */
                $q = $this->pdo->prepare(
                    'SELECT COUNT(*)
                     FROM vehicle
                     WHERE repo_year = ?
                     AND repo_month = ?
                     AND loan_number = ?'
                );

                $q->execute([
                    $d['repo_year'],
                    $d['repo_month'],
                    $d['loan_number']
                ]);

                $exists =
                    (int) $q->fetchColumn() > 0;

                $columns = array_keys($d);

                /*
                 * UPDATE existing vehicle
                 */
                if ($exists) {

                    $setParts = [];

                    foreach ($columns as $column) {
                        $setParts[] = "$column = ?";
                    }

                    $params = array_values($d);

                    $params[] = $d['repo_year'];
                    $params[] = $d['repo_month'];
                    $params[] = $d['loan_number'];

                    $sql =
                        'UPDATE vehicle
                         SET ' .
                        implode(', ', $setParts) .
                        ' WHERE repo_year = ?
                         AND repo_month = ?
                         AND loan_number = ?';

                    $s = $this->pdo->prepare($sql);

                    $s->execute($params);

                    $updated++;

                } else {

                    /*
                     * INSERT new vehicle
                     */
                    $placeholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($columns),
                            '?'
                        )
                    );

                    $sql =
                        'INSERT INTO vehicle (' .
                        implode(',', $columns) .
                        ') VALUES (' .
                        $placeholders .
                        ')';

                    $s = $this->pdo->prepare($sql);

                    $s->execute(
                        array_values($d)
                    );

                    $inserted++;
                }
            }

            /*
             * Commit
             */
            $this->pdo->commit();

            return [
                'totalRows' => $total,
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => 0,
                'errors' => []
            ];

        } catch (Throwable $e) {

            /*
             * Rollback if anything fails
             */
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new RuntimeException(
                'Excel upload failed: ' .
                $e->getMessage(),
                0,
                $e
            );
        }
    }
}