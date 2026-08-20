<?php
require_once __DIR__ . '/../helpers/response.php';
class ReportController {
 public function __construct(private ReportService $s,private ExcelReportService $excel){}
 public function summary(){jsonResponse($this->s->summary((string)queryParam('agencyId','')));}
 public function finance(){jsonResponse($this->s->finance((string)queryParam('agencyId',''),queryParam('finance'),queryParam('branch')));}
 public function monthly(){jsonResponse($this->s->monthly((string)queryParam('agencyId',''),(string)queryParam('year',''),(string)queryParam('month','')));}
 public function activity(){jsonResponse($this->s->userActivity((string)queryParam('agencyId','')));}
 public function financeList(){jsonResponse($this->s->financeList((string)queryParam('agencyId','')));}
 public function branchList(){jsonResponse($this->s->branchList((string)queryParam('agencyId',''),(string)queryParam('finance','')));}
 public function vehicles(){jsonResponse($this->s->vehicles((string)queryParam('agencyId',''),queryParam('finance'),queryParam('branch'),queryParam('year'),queryParam('month'),(string)queryParam('status','ALL')));}
 public function userReport(){jsonResponse($this->s->userReport((string)queryParam('userEmail','')));}
 public function financeExcel($agency){$rows=$this->s->finance($agency,queryParam('finance'),queryParam('branch'));$data=array_map(fn($r)=>[$r['finance'],$r['branch'],$r['totalVehicles'],$r['repoMarkedCount'],$r['parkedCount'],$r['releasedCount']],$rows);$this->excel->download('Finance_Report.xlsx','Finance Report',['Finance','Branch','Vehicles','Repo Marked','Parked','Released'],$data);}
 public function activityExcel($agency){$rows=$this->s->userActivity($agency);$data=array_map(fn($r)=>[$r['userName'],$r['userEmail'],$r['totalSearches'],$r['repoMarkedCount'],$r['parkedCount'],$r['releasedCount'],$r['lastSearchTime']],$rows);$this->excel->download('User_Activity_Report.xlsx','User Activity Report',['User Name','Email','Total Searches','Repo Marked','Parked','Released','Last Search Time'],$data);}
 public function monthlyExcel($agency){$rows=$this->s->monthly($agency,(string)queryParam('year',''),(string)queryParam('month',''));$data=array_map(fn($r)=>[$r['repoYear'],$r['repoMonth'],$r['totalVehicles'],$r['repoMarkedCount'],$r['parkedCount'],$r['releasedCount']],$rows);$this->excel->download('Monthly_Report.xlsx','Monthly Report',['Repo Year','Repo Month','Vehicles','Repo Marked','Parked','Released'],$data);}
 public function userExcel(){ $r=$this->s->userReport((string)queryParam('userEmail',''));$this->excel->download('User_Report.xlsx','User Report',['Total Vehicle','Repo Marked','Parked','Released'],[[$r['totalVehicles'],$r['repoMarked'],$r['parked'],$r['released']]]); }
 public function yardExcel($yardId){$agency=(string)queryParam('agencyId','');$status=(string)queryParam('status','ALL');$rows=$this->s->yardVehicles((int)$yardId,$agency,$status);$data=[];$n=1;foreach($rows as $r)$data[]=[$n++,$r['vehicle_number']??'',$r['repo_status']??'',$r['yard_name']??''];$name=strtoupper($status)==='ALL'?'All':(strtolower($status)==='repo mark'?'Repo_Marked':(strtolower($status)==='parked'?'Parked':(strtolower($status)==='released'?'Released':str_replace(' ','_',$status))));$this->excel->download('Yard_Report_'.$name.'.xlsx','Yard Report',['Sr No','Vehicle Number','Status','Yard'],$data);}
}
