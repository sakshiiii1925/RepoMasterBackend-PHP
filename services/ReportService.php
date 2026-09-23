<?php
class ReportService {
    public function __construct(private PDO $pdo) {}
    public function summary(string $agency): array { $s=$this->pdo->prepare("SELECT COUNT(*) total, SUM(CASE WHEN LOWER(repo_status)='open list' THEN 1 ELSE 0 END) openlist, SUM(CASE WHEN LOWER(repo_status)='contacted' THEN 1 ELSE 0 END) contacted, SUM(CASE WHEN LOWER(repo_status)='repo mark' THEN 1 ELSE 0 END) repoMark, SUM(CASE WHEN LOWER(repo_status)='parked' THEN 1 ELSE 0 END) parked, SUM(CASE WHEN LOWER(repo_status)='released' THEN 1 ELSE 0 END) released FROM vehicle WHERE agency_id=?");$s->execute([$agency]);$r=$s->fetch();return ['totalVehicles'=>(int)$r['total'],'openlist'=>(int)$r['openlist'],'contacted'=>(int)$r['contacted'],'repoMark'=>(int)$r['repoMark'],'parked'=>(int)$r['parked'],'released'=>(int)$r['released']]; }
    public function finance(string $agency,?string $finance,?string $branch): array {$sql="SELECT finance,branch,COUNT(*) totalVehicles,SUM(CASE WHEN LOWER(repo_status)='repo mark' THEN 1 ELSE 0 END) repoMarkedCount,SUM(CASE WHEN LOWER(repo_status)='parked' THEN 1 ELSE 0 END) parkedCount,SUM(CASE WHEN LOWER(repo_status)='released' THEN 1 ELSE 0 END) releasedCount FROM vehicle WHERE agency_id=?";$p=[$agency];if($finance!==null&&$finance!==''){ $sql.=' AND finance=?';$p[]=$finance;}if($branch!==null&&$branch!==''){ $sql.=' AND branch=?';$p[]=$branch;}$sql.=' GROUP BY finance,branch ORDER BY finance,branch';$s=$this->pdo->prepare($sql);$s->execute($p);return array_map(fn($r)=>['finance'=>$r['finance'],'branch'=>$r['branch'],'totalVehicles'=>(int)$r['totalVehicles'],'repoMarkedCount'=>(int)$r['repoMarkedCount'],'parkedCount'=>(int)$r['parkedCount'],'releasedCount'=>(int)$r['releasedCount']],$s->fetchAll());}
   public function monthly(
    string $agency,
    string $year,
    string $month
): array {

    $month = str_pad(
        trim($month),
        2,
        '0',
        STR_PAD_LEFT
    );

    /*
     * Selected calendar month.
     *
     * Example:
     * year  = 2026
     * month = 09
     *
     * start = 2026-09-01 00:00:00
     * end   = 2026-10-01 00:00:00
     */

    $startDate = sprintf(
        '%s-%s-01 00:00:00',
        $year,
        $month
    );

    $endDate = date(
        'Y-m-d H:i:s',
        strtotime($startDate . ' +1 month')
    );

    $sql = "
        SELECT

            ? AS reportYear,
            ? AS reportMonth,

            /*
             * Total unique vehicles whose status
             * changed during this calendar month.
             */
            COUNT(
                DISTINCT CASE

                    WHEN
                        (
                            v.repo_marked_at >= ?
                            AND v.repo_marked_at < ?
                        )
                        OR
                        (
                            v.parked_at >= ?
                            AND v.parked_at < ?
                        )
                        OR
                        (
                            v.released_at >= ?
                            AND v.released_at < ?
                        )

                    THEN CONCAT(
                        v.repo_year,
                        '-',
                        v.repo_month,
                        '-',
                        v.loan_number
                    )

                END
            ) AS totalVehicles,

            /*
             * Repo Marked during selected month
             */
           COUNT(
    DISTINCT CASE
        WHEN
            UPPER(TRIM(v.repo_status)) = 'repo mark'
            AND v.repo_marked_at >= ?
            AND v.repo_marked_at < ?
        THEN CONCAT(
            v.repo_year,
            '-',
            v.repo_month,
            '-',
            v.loan_number
        )
    END
) AS repoMarkedCount,

            /*
             * Parked during selected month
             */
            COUNT(
                DISTINCT CASE

                    WHEN
                        v.parked_at >= ?
                        AND v.parked_at < ?

                    THEN CONCAT(
                        v.repo_year,
                        '-',
                        v.repo_month,
                        '-',
                        v.loan_number
                    )

                END
            ) AS parkedCount,

            /*
             * Released during selected month
             */
            COUNT(
                DISTINCT CASE

                    WHEN
                        v.released_at >= ?
                        AND v.released_at < ?

                    THEN CONCAT(
                        v.repo_year,
                        '-',
                        v.repo_month,
                        '-',
                        v.loan_number
                    )

                END
            ) AS releasedCount

        FROM vehicle v

        WHERE v.agency_id = ?

          AND (

                (
                    v.repo_marked_at >= ?
                    AND v.repo_marked_at < ?
                )

                OR

                (
                    v.parked_at >= ?
                    AND v.parked_at < ?
                )

                OR

                (
                    v.released_at >= ?
                    AND v.released_at < ?
                )

          )
    ";

    $params = [

        // Display year/month
        $year,
        (int)$month,

        // Total vehicles - repo mark
        $startDate,
        $endDate,

        // Total vehicles - parked
        $startDate,
        $endDate,

        // Total vehicles - released
        $startDate,
        $endDate,

        // Repo Marked count
        $startDate,
        $endDate,

        // Parked count
        $startDate,
        $endDate,

        // Released count
        $startDate,
        $endDate,

        // Agency
        $agency,

        // WHERE - repo mark
        $startDate,
        $endDate,

        // WHERE - parked
        $startDate,
        $endDate,

        // WHERE - released
        $startDate,
        $endDate
    ];

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute($params);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [[
        'repoYear' => $year,
        'repoMonth' => (int)$month,

        'totalVehicles' =>
            (int)($row['totalVehicles'] ?? 0),

        'repoMarkedCount' =>
            (int)($row['repoMarkedCount'] ?? 0),

        'parkedCount' =>
            (int)($row['parkedCount'] ?? 0),

        'releasedCount' =>
            (int)($row['releasedCount'] ?? 0)
    ]];
}
  public function userActivity(
    string $agency,
    ?string $fromDate = null,
    ?string $toDate = null,
    ?string $userEmail = null
): array {

    /*
     * ---------------------------------------------------------
     * 1. SEARCH ACTIVITY
     * ---------------------------------------------------------
     *
     * Search History is the source of truth for:
     *
     * - Total Searches
     * - Last Search Time
     *
     * It must NOT be used to determine Repo Mark / Parked.
     */
    $searchSql = "
        SELECT
            sh.user_name,
            sh.user_email,
            sh.agency_id,
            COUNT(*) AS total_searches,
            MAX(sh.search_time) AS last_search_time
        FROM search_history sh
        WHERE sh.agency_id = ?
    ";

    $searchParams = [$agency];

    if ($fromDate !== null && $fromDate !== '') {

        $searchSql .= "
            AND sh.search_time >= ?
        ";

        $searchParams[] = $fromDate . ' 00:00:00';
    }

    if ($toDate !== null && $toDate !== '') {

        $searchSql .= "
            AND sh.search_time < DATE_ADD(?, INTERVAL 1 DAY)
        ";

        $searchParams[] = $toDate;
    }

    if ($userEmail !== null && $userEmail !== '') {

        $searchSql .= "
            AND sh.user_email = ?
        ";

        $searchParams[] = $userEmail;
    }

    $searchSql .= "
        GROUP BY
            sh.user_name,
            sh.user_email,
            sh.agency_id
    ";

    $searchStmt = $this->pdo->prepare($searchSql);
    $searchStmt->execute($searchParams);

    $searchRows = $searchStmt->fetchAll(PDO::FETCH_ASSOC);


    /*
     * ---------------------------------------------------------
     * 2. ACTION ACTIVITY
     * ---------------------------------------------------------
     *
     * Repo Mark and Parked are based on the actual user who
     * performed the action.
     *
     * repo_marked_by = users.id
     * parked_by      = users.id
     */
    $actionSql = "
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.agency_id,

        COUNT(
            DISTINCT CASE
                WHEN v.repo_marked_by = u.id
                THEN CONCAT(
                    v.repo_year,
                    '-',
                    v.repo_month,
                    '-',
                    v.loan_number
                )
            END
        ) AS repo_mark_count,

        COUNT(
            DISTINCT CASE
                WHEN v.parked_by = u.id
                THEN CONCAT(
                    v.repo_year,
                    '-',
                    v.repo_month,
                    '-',
                    v.loan_number
                )
            END
        ) AS parked_count,

        COUNT(
            DISTINCT CASE
                WHEN v.released_by = u.id
                THEN CONCAT(
                    v.repo_year,
                    '-',
                    v.repo_month,
                    '-',
                    v.loan_number
                )
            END
        ) AS released_count

    FROM users u

    LEFT JOIN vehicle v
        ON v.agency_id = u.agency_id
       AND (
            v.repo_marked_by = u.id
            OR
            v.parked_by = u.id
            OR
            v.released_by = u.id
       )

    WHERE u.agency_id = ?
";

    $actionParams = [$agency];

    if ($userEmail !== null && $userEmail !== '') {

        $actionSql .= "
            AND u.email = ?
        ";

        $actionParams[] = $userEmail;
    }

    /*
     * Date filtering for actions.
     *
     * Repo Mark uses repo_marked_at.
     * Parked uses parked_at.
     */
    if (
    ($fromDate !== null && $fromDate !== '') ||
    ($toDate !== null && $toDate !== '')
) {

    if (
        $fromDate !== null &&
        $fromDate !== '' &&
        $toDate !== null &&
        $toDate !== ''
    ) {

        $actionSql .= "
            AND (
                (
                    v.repo_marked_by = u.id
                    AND v.repo_marked_at >= ?
                    AND v.repo_marked_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
                OR
                (
                    v.parked_by = u.id
                    AND v.parked_at >= ?
                    AND v.parked_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
                OR
                (
                    v.released_by = u.id
                    AND v.released_at >= ?
                    AND v.released_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
            )
        ";

        $actionParams[] = $fromDate . ' 00:00:00';
        $actionParams[] = $toDate;

        $actionParams[] = $fromDate . ' 00:00:00';
        $actionParams[] = $toDate;

        $actionParams[] = $fromDate . ' 00:00:00';
        $actionParams[] = $toDate;

    } elseif (
        $fromDate !== null &&
        $fromDate !== ''
    ) {

        $actionSql .= "
            AND (
                (
                    v.repo_marked_by = u.id
                    AND v.repo_marked_at >= ?
                )
                OR
                (
                    v.parked_by = u.id
                    AND v.parked_at >= ?
                )
                OR
                (
                    v.released_by = u.id
                    AND v.released_at >= ?
                )
            )
        ";

        $actionParams[] = $fromDate . ' 00:00:00';
        $actionParams[] = $fromDate . ' 00:00:00';
        $actionParams[] = $fromDate . ' 00:00:00';

    } elseif (
        $toDate !== null &&
        $toDate !== ''
    ) {

        $actionSql .= "
            AND (
                (
                    v.repo_marked_by = u.id
                    AND v.repo_marked_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
                OR
                (
                    v.parked_by = u.id
                    AND v.parked_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
                OR
                (
                    v.released_by = u.id
                    AND v.released_at < DATE_ADD(?, INTERVAL 1 DAY)
                )
            )
        ";

        $actionParams[] = $toDate;
        $actionParams[] = $toDate;
        $actionParams[] = $toDate;
    }
}
           

    $actionSql .= "
        GROUP BY
            u.id,
            u.full_name,
            u.email,
            u.agency_id
    ";

    $actionStmt = $this->pdo->prepare($actionSql);
    $actionStmt->execute($actionParams);

    $actionRows = $actionStmt->fetchAll(PDO::FETCH_ASSOC);


    /*
     * ---------------------------------------------------------
     * 3. Convert action data to easy lookup
     * ---------------------------------------------------------
     */
    $actionsByEmail = [];

    foreach ($actionRows as $row) {

        $email = strtolower(
            trim((string)$row['email'])
        );

        $actionsByEmail[$email] = [
    'repoMarkedCount' => (int)$row['repo_mark_count'],
    'parkedCount' => (int)$row['parked_count'],
    'releasedCount' => (int)$row['released_count']
];
    }


    /*
     * ---------------------------------------------------------
     * 4. Combine Search + Action information
     * ---------------------------------------------------------
     */
    $result = [];

    foreach ($searchRows as $row) {

        $email = strtolower(
            trim((string)$row['user_email'])
        );

       $action = $actionsByEmail[$email] ?? [
    'repoMarkedCount' => 0,
    'parkedCount' => 0,
    'releasedCount' => 0
];
$result[] = [
    'userName' => (string)$row['user_name'],
    'userEmail' => (string)$row['user_email'],
    'agencyId' => (string)$row['agency_id'],

    'totalSearches' => (int)$row['total_searches'],

    'repoMarkedCount' => (int)$action['repoMarkedCount'],

    'parkedCount' => (int)$action['parkedCount'],

    'releasedCount' => (int)$action['releasedCount'],

    'lastSearchTime' => $row['last_search_time']
];
            
    }


    /*
     * ---------------------------------------------------------
     * 5. Include users who performed Repo Mark / Parked
     * but have no search_history record in the selected
     * date range.
     * ---------------------------------------------------------
     */
    foreach ($actionRows as $row) {

        $email = strtolower(
            trim((string)$row['email'])
        );

        $alreadyExists = false;

        foreach ($result as $existing) {

            if (
                strtolower(
                    trim((string)$existing['userEmail'])
                ) === $email
            ) {
                $alreadyExists = true;
                break;
            }
        }

        if ($alreadyExists) {
            continue;
        }

        /*
         * Only add if the user actually performed
         * an action.
         */
        if (
    (int)$row['repo_mark_count'] === 0 &&
    (int)$row['parked_count'] === 0 &&
    (int)$row['released_count'] === 0
) {
    continue;
}

        $result[] = [

            'userName' =>
                (string)$row['full_name'],

            'userEmail' =>
                (string)$row['email'],

            'agencyId' =>
                (string)$row['agency_id'],

            'totalSearches' =>
                0,

            'repoMarkedCount' =>
                (int)$row['repo_mark_count'],

            'parkedCount' =>
                (int)$row['parked_count'],

           'releasedCount' => (int)$row['released_count'],

            'lastSearchTime' =>
                null
        ];
    }


    /*
     * ---------------------------------------------------------
     * 6. Sort by total searches
     * ---------------------------------------------------------
     */
    usort(
        $result,
        function ($a, $b) {

            if (
                $a['totalSearches'] ===
                $b['totalSearches']
            ) {
                return strcmp(
                    $a['userName'],
                    $b['userName']
                );
            }

            return
                $b['totalSearches'] -
                $a['totalSearches'];
        }
    );

    return $result;
}

    public function financeList(string $agency): array {$s=$this->pdo->prepare('SELECT DISTINCT finance FROM vehicle WHERE agency_id=? ORDER BY finance');$s->execute([$agency]);return array_values(array_filter(array_column($s->fetchAll(),'finance'),fn($x)=>$x!==null&&$x!==''));}
    public function branchList(string $agency,string $finance): array {$s=$this->pdo->prepare('SELECT DISTINCT branch FROM vehicle WHERE agency_id=? AND finance=? ORDER BY branch');$s->execute([$agency,$finance]);return array_values(array_filter(array_column($s->fetchAll(),'branch'),fn($x)=>$x!==null&&$x!==''));}
 //vehicles
    public function vehicles(
    string $agency,
    ?string $finance,
    ?string $branch,
    ?string $year,
    ?string $month,
    string $status
): array {

    $sql = "
        SELECT
            v.vehicle_number,
            v.owner_name,
            v.loan_number,
            v.repo_status
        FROM vehicle v
        WHERE v.agency_id = ?
    ";

    $params = [$agency];


    /*
     * =========================================================
     * FINANCE FILTER
     * =========================================================
     */

    if ($finance !== null && $finance !== '') {

        $sql .= " AND v.finance = ?";
        $params[] = $finance;
    }

    if ($branch !== null && $branch !== '') {

        $sql .= " AND v.branch = ?";
        $params[] = $branch;
    }


    /*
     * =========================================================
     * MONTHLY REPORT VEHICLE DETAILS
     * =========================================================
     *
     * When the user clicks a monthly count:
     *
     * Repo Mark -> show CURRENT repo mark vehicles
     * Parked    -> show CURRENT parked vehicles
     * Released  -> show CURRENT released vehicles
     *
     * ALL       -> show vehicles that had any tracked
     *              action during the selected month.
     */

    if (
        $year !== null &&
        $year !== '' &&
        $month !== null &&
        $month !== ''
    ) {

        /*
         * -----------------------------------------------------
         * REPO MARK
         * -----------------------------------------------------
         *
         * IMPORTANT:
         * Do NOT use repo_marked_at here.
         *
         * We want only vehicles whose CURRENT status
         * is repo mark.
         */

        if (strcasecmp(trim($status), 'REPO MARK') === 0) {

            $sql .= "
                AND UPPER(TRIM(v.repo_status)) = 'REPO MARK'
            ";
        }


        /*
         * -----------------------------------------------------
         * PARKED
         * -----------------------------------------------------
         */

        elseif (strcasecmp(trim($status), 'PARKED') === 0) {

            $sql .= "
                AND UPPER(TRIM(v.repo_status)) = 'PARKED'
            ";
        }


        /*
         * -----------------------------------------------------
         * RELEASED
         * -----------------------------------------------------
         */

        elseif (strcasecmp(trim($status), 'RELEASED') === 0) {

            $sql .= "
                AND UPPER(TRIM(v.repo_status)) = 'RELEASED'
            ";
        }


        /*
         * -----------------------------------------------------
         * ALL
         * -----------------------------------------------------
         *
         * For ALL, keep the existing monthly action logic.
         */

        elseif (strtoupper(trim($status)) === 'ALL') {

            $month = str_pad(
                trim($month),
                2,
                '0',
                STR_PAD_LEFT
            );

            $startDate = sprintf(
                '%s-%s-01 00:00:00',
                $year,
                $month
            );

            $endDate = date(
                'Y-m-d H:i:s',
                strtotime($startDate . ' +1 month')
            );

            $sql .= "
                AND (
                    (
                        v.repo_marked_at >= ?
                        AND v.repo_marked_at < ?
                    )
                    OR
                    (
                        v.parked_at >= ?
                        AND v.parked_at < ?
                    )
                    OR
                    (
                        v.released_at >= ?
                        AND v.released_at < ?
                    )
                )
            ";

            $params[] = $startDate;
            $params[] = $endDate;

            $params[] = $startDate;
            $params[] = $endDate;

            $params[] = $startDate;
            $params[] = $endDate;
        }
    }


    /*
     * =========================================================
     * FINANCE REPORT / NORMAL REPORT
     * =========================================================
     *
     * If there is NO monthly year/month filter,
     * use the current repo_status.
     */

    elseif (strtoupper(trim($status)) !== 'ALL') {

        $sql .= "
            AND UPPER(TRIM(v.repo_status))
                = UPPER(TRIM(?))
        ";

        $params[] = $status;
    }


    /*
     * =========================================================
     * ORDER
     * =========================================================
     */

    $sql .= "
        ORDER BY v.vehicle_number
    ";


    /*
     * =========================================================
     * EXECUTE
     * =========================================================
     */

    $stmt = $this->pdo->prepare($sql);

    $stmt->execute($params);


    /*
     * =========================================================
     * RESPONSE
     * =========================================================
     */

    return array_map(
        fn($r) => [
            'vehicleNumber' => $r['vehicle_number'],
            'ownerName'     => $r['owner_name'],
            'loanNumber'    => $r['loan_number'],
            'repoStatus'    => $r['repo_status']
        ],
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
}

   public function userReport(string $email): array
{
    $email = trim($email);

    if ($email === '') {
        return [
            'totalVehicles' => 0,
            'repoMarked' => 0,
            'parked' => 0,
            'released' => 0
        ];
    }


    /*
     * ---------------------------------------------------------
     * 1. Find the user
     * ---------------------------------------------------------
     */
    $userStmt = $this->pdo->prepare("
        SELECT
            id,
            agency_id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $userStmt->execute([$email]);

    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return [
            'totalVehicles' => 0,
            'repoMarked' => 0,
            'parked' => 0,
            'released' => 0
        ];
    }

    $userId = (int)$user['id'];
    $agencyId = (string)($user['agency_id'] ?? '');


    /*
     * ---------------------------------------------------------
     * 2. Total Vehicles
     *
     * This remains based on unique vehicles searched by
     * this user.
     * ---------------------------------------------------------
     */
    $searchStmt = $this->pdo->prepare("
        SELECT COUNT(DISTINCT
            UPPER(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(vehicle_number, '-', ''),
                            '/',
                            ''
                        ),
                        '.',
                        ''
                    ),
                    ' ',
                    ''
                )
            )
        )
        FROM search_history
        WHERE user_email = ?
          AND agency_id = ?
    ");

    $searchStmt->execute([
        $email,
        $agencyId
    ]);

    $totalVehicles = (int)$searchStmt->fetchColumn();


    /*
     * ---------------------------------------------------------
     * 3. Repo Marked
     *
     * Actual action user = repo_marked_by
     * ---------------------------------------------------------
     */
    $repoStmt = $this->pdo->prepare("
        SELECT COUNT(*)
        FROM vehicle
        WHERE agency_id = ?
          AND repo_marked_by = ?
    ");

    $repoStmt->execute([
        $agencyId,
        $userId
    ]);

    $repoMarked = (int)$repoStmt->fetchColumn();


    /*
     * ---------------------------------------------------------
     * 4. Parked
     *
     * Actual action user = parked_by
     * ---------------------------------------------------------
     */
    $parkedStmt = $this->pdo->prepare("
        SELECT COUNT(*)
        FROM vehicle
        WHERE agency_id = ?
          AND parked_by = ?
    ");

    $parkedStmt->execute([
        $agencyId,
        $userId
    ]);

    $parked = (int)$parkedStmt->fetchColumn();


    /*
     * ---------------------------------------------------------
     * 5. Released
     *
     * We cannot correctly attribute Released to a user yet
     * because the vehicle table currently has no:
     *
     * released_by
     * released_at
     *
     * Therefore do NOT guess.
     * ---------------------------------------------------------
     */
    $released = 0;


    return [
        'totalVehicles' => $totalVehicles,
        'repoMarked' => $repoMarked,
        'parked' => $parked,
        'released' => $released
    ];
}
    public function yardVehicles(int $yardId,string $agency,string $status): array {$sql='SELECT v.*,y.yard_name,y.yard_address,y.yard_manager_name,y.yard_contact_no,y.agency_id yard_agency_id FROM vehicle v LEFT JOIN yard y ON y.id=v.yard_id WHERE v.yard_id=? AND v.agency_id=?';$p=[$yardId,$agency];if(strtoupper($status)!=='ALL'){$sql.=' AND UPPER(v.repo_status)=UPPER(?)';$p[]=$status;}$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll();}
}
