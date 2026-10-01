<?php

namespace App\Services\Payroll;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRecord;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WorkforceService
{
    public function recordAttendance(int $stationId,int $employeeId,string $date,?string $checkIn,?string $checkOut,string $status='present',?string $notes=null): AttendanceLog
    {
        return DB::transaction(function()use($stationId,$employeeId,$date,$checkIn,$checkOut,$status,$notes){
            Employee::query()->whereKey($employeeId)->where('station_id',$stationId)->firstOrFail();
            if($checkIn&&$checkOut&&strtotime($checkOut)<strtotime($checkIn))throw new RuntimeException('Check-out cannot precede check-in.');
            $seconds=($checkIn&&$checkOut)?strtotime($checkOut)-strtotime($checkIn):0;
            $hours=Decimal::normalize(intdiv($seconds,3600).'.'.str_pad((string)intdiv(($seconds%3600)*10000,3600),4,'0',STR_PAD_LEFT));
            return AttendanceLog::updateOrCreate(['station_id'=>$stationId,'employee_id'=>$employeeId,'attendance_date'=>$date],['check_in'=>$checkIn,'check_out'=>$checkOut,'worked_hours'=>$hours,'status'=>$status,'notes'=>$notes]);
        });
    }

    public function approveLeave(int $stationId,int $leaveId,int $approverId): LeaveRequest
    {
        return DB::transaction(function()use($stationId,$leaveId,$approverId){
            $leave=LeaveRequest::query()->whereKey($leaveId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($leave->status!=='pending')throw new RuntimeException('Only pending leave requests can be approved.');
            if($leave->ends_on < $leave->starts_on)throw new RuntimeException('Leave end date cannot precede start date.');
            $leave->update(['status'=>'approved','approved_by'=>$approverId]);
            return $leave->fresh();
        });
    }

    public function approveOvertime(int $stationId,int $overtimeId,int $approverId): OvertimeRecord
    {
        return DB::transaction(function()use($stationId,$overtimeId,$approverId){
            $ot=OvertimeRecord::query()->whereKey($overtimeId)->where('station_id',$stationId)->lockForUpdate()->firstOrFail();
            if($ot->status!=='pending')throw new RuntimeException('Only pending overtime can be approved.');
            if(Decimal::compare((string)$ot->amount,'0')<0)throw new RuntimeException('Overtime amount cannot be negative.');
            $ot->update(['status'=>'approved','approved_by'=>$approverId]);
            return $ot->fresh();
        });
    }
}