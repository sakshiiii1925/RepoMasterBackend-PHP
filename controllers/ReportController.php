<?php
require_once __DIR__ . '/../helpers/response.php';
class ReportController {
 public function __construct(private ReportService $s,private ExcelReportService $excel,private PdfReportService $pdf){}
 public function summary(){jsonResponse($this->s->summary((string)queryParam('agencyId','')));}
 public function finance(){jsonResponse($this->s->finance((string)queryParam('agencyId',''),queryParam('finance'),queryParam('branch')));}
 public function monthly(){jsonResponse($this->s->monthly((string)queryParam('agencyId',''),(string)queryParam('year',''),(string)queryParam('month','')));}
public function activity()
{
    $agencyId = (string) queryParam(
        'agencyId',
        ''
    );

    $fromDate = queryParam(
        'fromDate'
    );

    $toDate = queryParam(
        'toDate'
    );

    $userEmail = queryParam(
        'userEmail'
    );


    jsonResponse(
        $this->s->userActivity(
            $agencyId,
            $fromDate,
            $toDate,
            $userEmail
        )
    );
}
 public function financeList(){jsonResponse($this->s->financeList((string)queryParam('agencyId','')));}
 public function branchList(){jsonResponse($this->s->branchList((string)queryParam('agencyId',''),(string)queryParam('finance','')));}
 public function vehicles(){jsonResponse($this->s->vehicles((string)queryParam('agencyId',''),queryParam('finance'),queryParam('branch'),queryParam('year'),queryParam('month'),(string)queryParam('status','ALL')));}
 public function userReport(){jsonResponse($this->s->userReport((string)queryParam('userEmail','')));}

public function financeExcel($agency)
{
    $rows = $this->s->finance(
        $agency,
        queryParam('finance'),
        queryParam('branch')
    );

    $data = array_map(
        fn($r) => [
            $r['finance'] ?? '',
            $r['branch'] ?? '',
            (int)($r['totalVehicles'] ?? 0),
            (int)($r['repoInitAndParkedCount'] ?? 0),
            (int)($r['releasedCount'] ?? 0)
        ],
        $rows
    );

    $this->excel->download(
        'Finance_Report.xlsx',
        'Finance Report',
        [
            'Finance',
            'Branch',
            'Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        $data
    );
}

public function activityExcel($agency)
{
    $fromDate = queryParam('fromDate');
    $toDate = queryParam('toDate');
    $userEmail = queryParam('userEmail');

    $rows = $this->s->userActivity(
        $agency,
        $fromDate,
        $toDate,
        $userEmail
    );

    $data = array_map(
        function ($r) {
            return [
                $r['userName'] ?? '',
                $r['userEmail'] ?? '',
                $r['totalSearches'] ?? 0,
              $r['repoInitAndParkedCount'] ?? 0,
                $r['releasedCount'] ?? 0,
                $r['lastSearchTime'] ?? ''
            ];
        },
        $rows
    );

    $this->excel->download(
        'Agent_Report.xlsx',
        'Agent Report',
        [
            'User Name',
            'Email',
            'Total Searches',
            'Repo init and Parked',
            'Released',
            'Last Search Time'
        ],
        $data
    );
}

public function monthlyExcel($agency)
{
    $year = (string) queryParam('year', '');
    $month = (string) queryParam('month', '');

    $rows = $this->s->monthly($agency, $year, $month);

    $data = array_map(
        fn($r) => [
            $r['repoYear'] ?? '',
            $r['repoMonth'] ?? '',
            (int)($r['totalVehicles'] ?? 0),
            (int)($r['repoInitAndParkedCount'] ?? 0),
            (int)($r['releasedCount'] ?? 0)
        ],
        $rows
    );

    $this->excel->download(
        'Monthly_Report.xlsx',
        'Monthly Report',
        [
            'Year',
            'Month',
            'Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        $data
    );
}

public function userExcel()
{
    $r = $this->s->userReport(
        (string)queryParam('userEmail', '')
    );

    $this->excel->download(
        'User_Report.xlsx',
        'User Report',
        [
            'Total Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        [[
            (int)($r['totalVehicles'] ?? 0),
            (int)($r['repoMarked'] ?? 0),
            (int)($r['released'] ?? 0)
        ]]
    );
}
 public function yardExcel($yardId){$agency=(string)queryParam('agencyId','');$status=(string)queryParam('status','ALL');$rows=$this->s->yardVehicles((int)$yardId,$agency,$status);$data=[];$n=1;foreach($rows as $r)$data[]=[$n++,$r['vehicle_number']??'',$r['repo_status']??'',$r['yard_name']??''];$name=strtoupper($status)==='ALL'?'All':(strtolower($status)==='Repo init and Parked'?'Repo_init_and_Parked':(strtolower($status)==='released'?'Released':str_replace(' ','_',$status)));$this->excel->download('Yard_Report_'.$name.'.xlsx','Yard Report',['Sr No','Vehicle Number','Status','Yard'],$data);}
 public function financePdf($agency)
{
    $rows = $this->s->finance(
        $agency,
        queryParam('finance'),
        queryParam('branch')
    );

    $data = array_map(
        fn($r) => [
            $r['finance'],
            $r['branch'],
            $r['totalVehicles'],
          $r['repoInitAndParkedCount'] ?? 0,
            $r['releasedCount']
        ],
        $rows
    );

    $this->pdf->download(
        'Finance_Report.pdf',
        'Finance Report',
        [
            'Finance',
            'Branch',
            'Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        $data
    );
}
public function monthlyPdf($agency)
{
    $year = (string) queryParam(
        'year',
        ''
    );

    $month = (string) queryParam(
        'month',
        ''
    );

    $rows = $this->s->monthly(
        $agency,
        $year,
        $month
    );

    $data = array_map(
        fn($r) => [
            $r['repoYear'],
            $r['repoMonth'],
            (int)$r['totalVehicles'],
            $r['repoInitAndParkedCount'] ?? 0,
            (int)$r['releasedCount']
        ],
        $rows
    );

    $this->pdf->download(
        'Monthly_Report.pdf',
        'Monthly Report',
        [
            'Year',
            'Month',
            'Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        $data
    );
}
public function activityPdf($agency)
{
    $fromDate = queryParam('fromDate');
    $toDate = queryParam('toDate');
    $userEmail = queryParam('userEmail');

    $rows = $this->s->userActivity(
        $agency,
        $fromDate,
        $toDate,
        $userEmail
    );

    $data = array_map(
        function ($r) {

            return [
                $r['userName'] ?? '',
                $r['userEmail'] ?? '',
                $r['totalSearches'] ?? 0,
                $r['repoInitAndParkedCount'] ?? 0,
                $r['releasedCount'] ?? 0,
                $r['lastSearchTime'] ?? ''
            ];
        },
        $rows
    );

    $this->pdf->download(
        'Agent_Report.pdf',
        'User Activity Report',
        [
            'User Name',
            'Email',
            'Total Searches',
            'Repo init and Parked',
            'Released',
            'Last Search Time'
        ],
        $data
    );
}
public function vehiclesPdf($agency)
{
    $finance = queryParam('finance');
    $branch = queryParam('branch');
    $year = queryParam('year');
    $month = queryParam('month');

    $status = (string)queryParam(
        'status',
        'ALL'
    );

    $rows = $this->s->vehicles(
        $agency,
        $finance,
        $branch,
        $year,
        $month,
        $status
    );

    $data = array_map(
        fn($r) => [
            $r['vehicleNumber'] ?? '',
            $r['ownerName'] ?? '',
            $r['loanNumber'] ?? '',
            $r['repoStatus'] ?? ''
        ],
        $rows
    );

    $this->pdf->download(
        'Vehicle_Report.pdf',
        'Vehicle Report',
        [
            'Vehicle Number',
            'Owner Name',
            'Loan Number',
            'Status'
        ],
        $data
    );
}
public function userPdf()
{
    $email = (string)queryParam(
        'userEmail',
        ''
    );

    $r = $this->s->userReport(
        $email
    );

   $data = [[
    (int)($r['totalVehicles'] ?? 0),
    (int)($r['repoMarked'] ?? 0),
    (int)($r['released'] ?? 0)
]];

    $this->pdf->download(
        'User_Report.pdf',
        'My Report',
        [
            'Total Vehicles',
            'Repo init and Parked',
            'Released'
        ],
        $data
    );
}
}
