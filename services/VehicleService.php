<?php

require_once __DIR__ . '/../helpers/mappers.php';

class VehicleService
{
    public function __construct(private PDO $pdo) {}

    private function baseSelect(): string
    {
        return "SELECT v.*, y.yard_name, y.yard_address, y.yard_manager_name, y.yard_contact_no, y.agency_id AS yard_agency_id FROM vehicle v LEFT JOIN yard y ON y.id=v.yard_id";
    }

    private function findVehicleRow(string $keyword): ?array
    {
        $normalized = strtoupper(str_replace(['-','/','.',' '], '', $keyword));
        $sql = $this->baseSelect()." WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(v.vehicle_number,'-',''),'/',''),'.',''),' ',''))=? OR UPPER(v.loan_number)=UPPER(?) LIMIT 1";
        $s=$this->pdo->prepare($sql); $s->execute([$normalized,$keyword]); return $s->fetch() ?: null;
    }

    public function getVehicle(string $keyword): ?array { $r=$this->findVehicleRow($keyword); return $r ? vehicleRow($r) : null; }
    public function updateStatus(string $keyword,string $status): ?array { $r=$this->findVehicleRow($keyword); if(!$r) return null; $s=$this->pdo->prepare('UPDATE vehicle SET repo_status=? WHERE repo_year=? AND repo_month=? AND loan_number=?'); $s->execute([$status,$r['repo_year'],$r['repo_month'],$r['loan_number']]); return vehicleRow($this->findVehicleRow($keyword)); }

    private function vehicleData(array $v): array
    {
        $id=$v['id']??[];
        return [
            'repo_year'=>$id['repoYear']??$v['repoYear']??null,
            'repo_month'=>$id['repoMonth']??$v['repoMonth']??null,
            'loan_number'=>$id['loanNumber']??$v['loanNumber']??null,
            'vehicle_number'=>$v['vehicleNumber']??null,'chassis_number'=>$v['chassisNumber']??null,'engine_number'=>$v['engineNumber']??null,'color'=>$v['color']??null,'manufacture_name'=>$v['manufactureName']??null,'model'=>$v['model']??null,'vehicle_make'=>$v['vehicleMake']??null,'vehicle_type'=>$v['vehicleType']??null,'owner_name'=>$v['ownerName']??null,'owner_mobile'=>$v['ownerMobile']??null,'customer_address'=>$v['customerAddress']??null,'customer_area'=>$v['customerArea']??null,'agency_id'=>$v['agencyId']??null,'agencyid_give_by_finance'=>$v['agencyIdGiveByFinance']??null,'agency_name'=>$v['agencyName']??null,'agency_manager'=>$v['agencyManager']??null,'agency_mobile'=>$v['agencyMobile']??null,'agency_mobile2'=>$v['agencyMobile2']??null,'executive_name'=>$v['executiveName']??null,'repo_status'=>$v['repoStatus']??null,'allocation_dpd'=>$v['allocationDpd']??null,'finance'=>$v['finance']??null,'branch'=>$v['branch']??null,'area'=>$v['area']??null,'area_manager_name'=>$v['areaManagerName']??null,'area_manager_mobile_no'=>$v['areaManagerMobileNo']??null,'area_manager_email_id'=>$v['areaManagerEmailId']??null,'contact_name2'=>$v['contactName2']??null,'contact_name2_designation'=>$v['contactName2Designation']??null,'contact_name2_mobile_no'=>$v['contactName2MobileNo']??null,'region_manager_name'=>$v['regionManagerName']??null,'region_manager_mobile_no'=>$v['regionManagerMobileNo']??null,'region_manager_email_id'=>$v['regionManagerEmailId']??null,'ref_letter'=>$v['refLetter']??null,'total_charges'=>$v['totalCharges']??null,'upload_by'=>$v['uploadBy']??null,'upload_date'=>$v['uploadDate']??null,'yard_id'=>$v['yardId']??null,
        ];
    }

    public function addVehicle(array $v): array { $d=$this->vehicleData($v); $cols=array_keys($d); $sql='INSERT INTO vehicle ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')'; $s=$this->pdo->prepare($sql); $s->execute(array_values($d)); $r=$this->findByPk($d['repo_year'],$d['repo_month'],$d['loan_number']); return vehicleRow($r); }
    public function addAllVehicles(array $vehicles): array { $this->pdo->beginTransaction(); try { $out=[]; foreach($vehicles as $v)$out[]=$this->addVehicle($v); $this->pdo->commit(); return $out; } catch(Throwable $e){$this->pdo->rollBack();throw $e;} }
    private function findByPk(?string $year,?string $month,?string $loan): ?array { $s=$this->pdo->prepare($this->baseSelect().' WHERE v.repo_year=? AND v.repo_month=? AND v.loan_number=?');$s->execute([$year,$month,$loan]);return $s->fetch()?:null; }
    public function getAllVehicles(string $agencyId): array { $s=$this->pdo->prepare($this->baseSelect().' WHERE v.agency_id=? ORDER BY v.vehicle_number');$s->execute([$agencyId]);return array_map(fn($r)=>vehicleRow($r),$s->fetchAll()); }
    public function updateVehicle(string $keyword,array $u): array { $r=$this->findVehicleRow($keyword); if(!$r) throw new RuntimeException('Vehicle Not Found with number: '.$keyword); $fields=['owner_name','owner_mobile','manufacture_name','model','color','engine_number','chassis_number','agency_name','agency_mobile','agency_mobile2','agency_manager','agency_id','vehicle_make','vehicle_type','branch','customer_area','customer_address','executive_name','allocation_dpd','repo_status','area_manager_name','area_manager_mobile_no','area_manager_email_id','contact_name2','contact_name2_designation','contact_name2_mobile_no','region_manager_name','region_manager_mobile_no','region_manager_email_id','finance','ref_letter','total_charges']; $map=['owner_name'=>'ownerName','owner_mobile'=>'ownerMobile','manufacture_name'=>'manufactureName','model'=>'model','color'=>'color','engine_number'=>'engineNumber','chassis_number'=>'chassisNumber','agency_name'=>'agencyName','agency_mobile'=>'agencyMobile','agency_mobile2'=>'agencyMobile2','agency_manager'=>'agencyManager','agency_id'=>'agencyId','vehicle_make'=>'vehicleMake','vehicle_type'=>'vehicleType','branch'=>'branch','customer_area'=>'customerArea','customer_address'=>'customerAddress','executive_name'=>'executiveName','allocation_dpd'=>'allocationDpd','repo_status'=>'repoStatus','area_manager_name'=>'areaManagerName','area_manager_mobile_no'=>'areaManagerMobileNo','area_manager_email_id'=>'areaManagerEmailId','contact_name2'=>'contactName2','contact_name2_designation'=>'contactName2Designation','contact_name2_mobile_no'=>'contactName2MobileNo','region_manager_name'=>'regionManagerName','region_manager_mobile_no'=>'regionManagerMobileNo','region_manager_email_id'=>'regionManagerEmailId','finance'=>'finance','ref_letter'=>'refLetter','total_charges'=>'totalCharges']; $sets=[];$params=[];foreach($fields as $f){$sets[]="$f=?";$params[]=$u[$map[$f]]??null;}$params[]=$r['repo_year'];$params[]=$r['repo_month'];$params[]=$r['loan_number'];$s=$this->pdo->prepare('UPDATE vehicle SET '.implode(',',$sets).' WHERE repo_year=? AND repo_month=? AND loan_number=?');$s->execute($params);return vehicleRow($this->findVehicleRow($keyword)); }
    public function deleteVehicle(string $keyword): void { $r=$this->findVehicleRow($keyword); if(!$r) throw new RuntimeException('Vehicle Not Found with number: '.$keyword); $s=$this->pdo->prepare('DELETE FROM vehicle WHERE repo_year=? AND repo_month=? AND loan_number=?');$s->execute([$r['repo_year'],$r['repo_month'],$r['loan_number']]); }
    public function searchVehicleNumbers(string $keyword): array { $n=strtoupper(str_replace(['-','/','.',' '],'',$keyword));$s=$this->pdo->prepare($this->baseSelect()." WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(v.vehicle_number,'-',''),'/',''),'.',''),' ','')) LIKE CONCAT('%',?,'%') OR UPPER(v.loan_number) LIKE CONCAT('%',UPPER(?),'%') ORDER BY v.vehicle_number LIMIT 50");$s->execute([$n,$keyword]);return array_map(fn($r)=>vehicleRow($r),$s->fetchAll()); }
    public function assignVehicleToYard(string $vehicleNumber,int $yardId): bool { $s=$this->pdo->prepare('UPDATE vehicle SET yard_id=? WHERE vehicle_number=?');$s->execute([$yardId,$vehicleNumber]);return $s->rowCount()>0; }
    public function getVehiclesByYard(int $yardId,string $agencyId): array { $s=$this->pdo->prepare($this->baseSelect().' WHERE v.yard_id=? AND v.agency_id=? ORDER BY v.vehicle_number');$s->execute([$yardId,$agencyId]);return array_map(fn($r)=>vehicleRow($r),$s->fetchAll()); }
    public function removeVehicleFromYard(string $vehicleNumber): bool { $s=$this->pdo->prepare('UPDATE vehicle SET yard_id=NULL WHERE vehicle_number=?');$s->execute([$vehicleNumber]);return $s->rowCount()>0; }
}
