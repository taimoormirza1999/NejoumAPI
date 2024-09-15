<?php

namespace App\Libraries;

use Illuminate\Support\Facades\DB;
use stdClass;
use Aws\S3\S3Client;
use App\Libraries\Constants;
use Image;
class Helpers
{

    public static function call_slaves()
    {
        $Slave_system   = DB::Table('slave_system')
            ->select('slave_system_link')
            ->where([
                ['slave_system_status', '=', '1']
            ])
            ->get()->toArray();
        return $Slave_system;
    }

    public static function get_internal_url($nejoum_carId, $img_name, $type = '')
    {
        $slaves = self::call_slaves();
        $p      = urlencode($img_name);
        foreach ($slaves as $key => $slave) {
            $api_url = $slave->slave_system_link . "v1/Slave_API/get_image_url".$type."/" . $nejoum_carId . '/' . $p;
        }

        $connection = curl_init();
        curl_setopt($connection, CURLOPT_URL, $api_url);
        curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);

        $get_data = curl_exec($connection);
        curl_close($connection);
        return $get_data;
    }

    public static function get_car_cost($account_id, $car_id)
    {
        $result = DB::table('accounttransaction')
            ->select('Debit')
            ->where('accounttransaction.AccountID', $account_id)
            ->where('car_id', $car_id)
            ->where('accounttransaction.deleted', 0)
            ->where('accounttransaction.car_step', 1)
            ->where('accounttransaction.type_transaction', 3)
            ->first();

        return empty($result) ? 0 : $result->Debit;
    }

    public static function get_customer_storage_fine($customer_id, $date_to)
    {
        $query = DB::table('car')
            ->select(DB::raw('SUM(car_storage_fine.amount) as storage_fine'))
            ->leftJoin('car_storage_fine', 'car_storage_fine.car_id', '=', 'car.id')
            ->join('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->where('car.customer_id', $customer_id)
            ->where('car.final_payment_status', 0)
            ->where('car.deleted', 0);

        if (!empty($date_to)) {
            $query->whereRaw("DATE(car_total_cost.create_date) < '$date_to' ");
        }
        return $query->first()->storage_fine;
    }

    public static function get_final_bill_date($car_id)
    {
        return DB::table('final_bill')
            ->select('create_date')
            ->where('final_bill.car_id', $car_id)
            ->first()->create_date;
    }

    public static function get_customer_account_id($customer_id)
    {
        return DB::table('customer')
            ->select('account_id')
            ->where('customer_id', $customer_id)
            ->first()
            ->account_id;
    }

    public static function get_customer_data($customer_id){
        return DB::table('customer')->where('customer_id', $customer_id)->first();
    }

    public static function customer_account_id_by_car($car_id)
    {
        return DB::table('car')
            ->join('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->select('customer.account_id')
            ->where('car.id', $car_id)
            ->first()
            ->account_id;
    }

    public static function get_exchange_rate()
    {
        return DB::table('convert_to_dollar')->select('*')->first();
    }

    public static function format_money($num, $curreny = false)
    {
        return number_format($num, 2) . ($curreny ? ' ' . $curreny : '');
    }

    public static function get_usd_rate()
    {
        return Helpers::get_exchange_rate()->dollar_price;
    }

    public static function get_cad_rate()
    {
        return Helpers::get_exchange_rate()->dollar_price_cad;
    }

    public static function generataTransactionNo($journal)
    {
        $journal = !is_array($journal) ? (array)$journal : $journal;
        $id = $journal['id'];
        $serial_no = $journal['serial_no'];
        $number = $serial_no > 0 ? str_pad($serial_no, 6, '0', STR_PAD_LEFT) : $id;
        $car_step = $journal['car_step'];
        $typePay = $journal['typePay'];
        $rec_type = $journal['rec_type'];
        $year = date('Y', strtotime($journal['create_date']));
        $name = '';

        if ($rec_type == 1)
            $name =  'RVG';
        else if ($rec_type == 2 && $typePay == 2)
            $name =  'PVC';
        else if ($rec_type == 2 && $typePay == 1)
            $name =  'PVH';
        else if ($rec_type == 3 && $car_step == 0)
            $name =  'JVM';
        else if ($rec_type == 3 && $car_step > 0)
            $name =  'JVA';

        return $name == '' ? "#$number" : "$name$year - $number";
    }

    public static function getPDF($html)
    {
        return \PDF::loadHTML($html)->setPaper('a4');
    }

    public static function lang_array($array)
    {
        foreach ($array as $key => $str) {
            $array[$key] = __($str);
        }
        return implode(' ', $array);
    }

    public static function is_arabic()
    {
        return app('translator')->getLocale() == 'ar';
    }

    public static function is_english()
    {
        return app('translator')->getLocale() == 'en';
    }

    public static function getRawSql($query)
    {
        return vsprintf(str_replace(array('?'), array('\'%s\''), $query->toSql()), $query->getBindings());
    }

    public static function getExtraDetailLabels($extraDetail)
    {
        $data[] = array(
            'service_labal_en' => 'General Extra',
            'service_labal_ar' =>  trans('General Extra', [], 'ar'),
            'value'            => $extraDetail->general_extra,
            'note'             => $extraDetail->general_extra_note
        );
        $data[] = array(
            'service_labal_en' => 'Auto Extra',
            'service_labal_ar' =>  trans('Auto Extra', [], 'ar'),
            'value'            => $extraDetail->auto_extra,
            'note'             => ''
        );
        $data[] = array(
            'service_labal_en' => 'VAT',
            'service_labal_ar' =>  trans('VAT', [], 'ar'),
            'value'            => $extraDetail->sale_vat,
            'note'             => ''
        );
        $data[] = array(
            'service_labal_en' => 'Recovery',
            'service_labal_ar' =>  trans('Recovery', [], 'ar'),
            'value'            => $extraDetail->recovery_price,
            'note'             => ''
        );
        $data[] = array(
            'service_labal_en' => 'Towing Fines',
            'service_labal_ar' =>  trans('Towing Fines', [], 'ar'),
            'value'            => $extraDetail->towing_fine,
            'note'             => ''
        );
        $data[] = array(
            'service_labal_en' => 'Shipping Commission',
            'service_labal_ar' =>  trans('Shipping Commission', [], 'ar'),
            'value'            => $extraDetail->shipping_commission,
            'note'             => ''
        );
        return $data;
    }

    public static function getCarTransactionLabels($transactions, $exchange_rate = 1)
    {
        $list = [];
        $firstRow = reset($transactions);

        if (!empty($firstRow->totalShipping)) {
            $newRow = new stdClass();
            $newRow->Debit = $firstRow->totalShipping;
            $newRow->Credit = 0;
            $newRow->car_step = 5;
            $newRow->sort_order = 2;
            $newRow->car_id = $firstRow->car_id;
            $transactions[] = $newRow;
        }

        if (!empty($firstRow->totalClearance)) {
            $newRow = new stdClass();
            $newRow->Debit = $firstRow->totalClearance - $firstRow->clearanceDiscount;
            $newRow->Credit = 0;
            $newRow->car_step = 8;
            $newRow->sort_order = 3;
            $transactions[] = $newRow;
        }

        if (!empty($firstRow->storageAfterPayment)) {
            $list[] = [
                'credit' => 0,
                'debit' => $firstRow->storageAfterPayment,
                'service_label_en' => 'Storage After Payment -',
                'service_label_ar' => trans('Storage After Payment', [], 'ar'),
            ];
        }

        if (!empty($firstRow->totalStorage)) {
            $newRow = new stdClass();
            $newRow->Debit = $firstRow->totalStorage;
            $newRow->Credit = 0;
            $newRow->car_step = 20;
            $transactions[] = $newRow;
        }


        foreach ($transactions as $key => $row) {
            $row->sort_order = isset($row->sort_order) ? $row->sort_order : 50;
            $row->carAccountingNotes = $firstRow->carAccountingNotes;
            $labels = Helpers::getSingleTransactionLabel($row);

            $list[] = [
                'debit' => round($row->Debit / $exchange_rate, 2),
                'credit' => round($row->Credit / $exchange_rate, 2),
                'car_step' => $row->car_step,
                'service_label_en' => $labels['service_label_en'],
                'service_label_ar' => $labels['service_label_ar'],
                'note' => $labels['note'],
                'sort_order' => $labels['sort_order']
            ];
        }

        usort($list, function($a, $b){
            return $a['sort_order'] > $b['sort_order'];
        });

        return $list;
    }

    public static function getSingleTransactionLabel($row)
    {
        $car_step = $row->car_step;
        $type_transaction = $row->type_transaction;
        $service_label_en = $service_label_ar = $note = '';
        $carAccountingNotes = $row->carAccountingNotes;

        $carStepLabels = [
            2 => 'Towing',
            4 => 'Extra Towing 1',
            5 => 'Shipping',
            6 => 'Loading',
            8 => 'Clearance',
            10 => 'Transport',
            11 => 'Multiple Towing',
            20 => 'Auction Fines',
            24 => 'Late Payment',
            23 => 'Foklift',
            25 => 'Car Carrier Storages',
            26 => 'Mailing Fee',
            50 => 'BOS',
            100 => 'BOS',
            101 => 'Extra Towing',
            102 => 'Extra Loading',
            103 => 'Extra Shipping',
            104 => 'Extra Clearance',
            105 => 'Extra Transport',
            106 => 'Discount',
            107 => 'Custom',
            108 => 'Clearance Discount',
            116 => 'Customer Discount',
            160 => 'VAT',
            161 => 'Storage',
            170 => 'Security Deposit for Papers',
            171 => 'payment Security Deposit',
            173 => 'Security Deposit Refund for Papers',
            174 => 'Security Deposit Refund for Papers',
            180 => 'Corona',
            300 => 'Towing Discount',
            301 => 'BOS Discount',
            302 => 'Loading Discount',
            303 => 'Shipping Discount',
            304 => 'Clearance Discount',
            305 => 'Custom Discount',
            306 => 'VAT Discount',
            307 => 'Commission Discount',
            307 => 'Extra Discount',
            310 => 'Storage Discount',
            311 => 'Towing Fine Discount',
            312 => 'Auto Discount',
            313 => 'Extra Clearance Discount',
            314 => 'Extra Towing Discount',
            1111 => 'Extra',
            1112 => 'Recovery',
            1113 => 'Special Code',
            1114 => 'Extra Shipping',
            11445 => 'Custom Invoice Attestation',
            11446 => 'Inspection',
            1999 => 'Car Damaged',
            22222 => 'Shipping Commission',

        ];

        $carStepNotes = [
            5 => 'shipping_notes',
            6 => ['loading_notes', 'loading2_notes'],
            8 => 'clearance_notes',
            106 => 'discount_notes',
            316 => 'discount_notes',
            110 => 'discount_notes',
            160 => 'vat_notes',
            101 => 'posted_notes',
            1111 => 'generalextra_notes',
        ];

        if ($car_step == 0 and $type_transaction == 3) {
            $service_label_en = $row->Description;
            $service_label_ar = $row->DescriptionAR;
        } else if ($car_step == 1 and $type_transaction == 3) {
            $service_label_en = 'Car Price';
            $row->sort_order = 1;
        } else if ($car_step == 0 and $type_transaction == 1) {
            $service_label_en = 'Amount Payment';
            $row->sort_order = 4;
        } else if ($car_step == 1 and $type_transaction == 1) {
            $service_label_en = 'Car Payment';
            $row->sort_order = 10;
        } else if (isset($carStepLabels[$car_step])) {
            $service_label_en = $carStepLabels[$car_step];

            if(isset($carStepNotes[$car_step])){
                $noteNames = $carStepNotes[$car_step];
                if(is_array($noteNames)){
                    foreach($noteNames as $noteName){
                        $note .= ', '. $carAccountingNotes[$noteName];
                    }
                }
                else{
                    $note = $carAccountingNotes[$noteNames];
                }
            }

        } else if ($car_step >= 300 && $car_step <= 305) {
            $service_label_en = 'Discount';
            $note = $carAccountingNotes['discount_notes'];
        } else if (!empty($row->car_step_name_en)) {
            $service_label_en = $row->car_step_name_en;
            $service_label_ar = $row->car_step_name_ar;
            $note = '';
        }
        if($car_step == '5'){
            $extraNotes = self::autoExtraNote($row->car_id);
            $note = $note?$note.','.$extraNotes:$extraNotes;
        }

        return [
            'service_label_en' => $service_label_en,
            'service_label_ar' => !empty($service_label_ar) ? $service_label_ar : trans($service_label_en, [], 'ar'),
            'note' => $note,
            'sort_order' => $row->sort_order,
        ];
    }

    public static function get_number_in_words($number, $level = 0, $attrs = [])
    {
        $number = $level == 0 ? number_format($number, 2, '.', '') : $number;
        $currency = !empty($attrs['currency']) ? $attrs['currency'] : 'Dirham';
        $subCurrency = !empty($attrs['subCurrency']) ? $attrs['subCurrency'] : 'Fils';

        $hyphen      = '-';
        $conjunction = '  ';
        $separator   = ' ';
        $negative    = 'Negative ';
        $decimal     = ' & ';
        $dictionary = array(0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety', 100 => 'Hundred', 1000 => 'Thousand', 1000000 => 'Million', 1000000000 => 'Billion');

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            trigger_error(
                'Number out of range! accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . self::get_number_in_words(-1 * $number, $level);
        }

        $string = $fraction = null;
        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }

                break;
            case $number < 1000:
                $hundreds  = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . self::get_number_in_words($remainder, $level + 1);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = self::get_number_in_words($numBaseUnits, $level + 1) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= self::get_number_in_words($remainder, $level + 1);
                }
                break;
        }

        if ($level == 0) {
            $string .= " $currency ";
        }

        if (null !== $fraction && $fraction != 0) {
            $string .= $decimal;
            $string = $string == "{$dictionary[0]} $currency $decimal" ? '' : $string;
            $words = array();

            switch (true) {
                case $fraction < 21:
                    $string .= $dictionary[$fraction];
                    break;
                case $fraction < 100:
                    $tens   = ((int) ($fraction / 10)) * 10;
                    $units  = $fraction % 10;
                    $string .= $dictionary[$tens];
                    if ($units) {
                        $string .= $hyphen . $dictionary[$units];
                    }
                    break;
                default:
                    break;
            }

            $string .= implode(' ', $words) . " $subCurrency";
        }

        return $string;
    }

    public static function edd($data, $exit = true){
        echo "<pre>";
        print_r($data);
        if($exit){
            exit();
        }
    }

    public static function getSpecialScenarioPorts(){
        $ports = \App\Models\CarAccounting::getSpecialScenarioPorts();
        return array_column($ports, 'port_id');
    }

    public static function scaleImageFile($file, $targetPath) {

       $mobile_Path = $targetPath.'app/'.$file;
        $newwidth = 480;
        $newheight = 360;
        $img = Image::make($targetPath.''.$file);
        $img->resize($newwidth, $newheight)->save($mobile_Path);
        if(file_exists($mobile_Path))
        {
          return 0;

        }else {
          return 1;
        }
    }

    public static function get_customer_storage_cars($customer_id)
    {
        $query = DB::table('car_storage_fine')
            ->select(DB::raw('car.id,car_storage_fine.amount'))
            ->join('car', 'car_storage_fine.car_id', '=', 'car.id')
            ->where('car.customer_id', $customer_id)
            ->where('car.deleted', 0);

        return $query->get()->toArray();
    }

    /**
     * Get the path to the public folder.
     *
     * @param  string $path
     * @return string
     */
    function public_path($path = '')
    {
        return env('PUBLIC_PATH', base_path('public')) . ($path ? '/' . $path : $path);
    }

    public static function send_notification_service($data)
    {
        if(self::is_localhost()) return true;
        $data['body_ar']=empty($data['body_ar'])?$data['body']:$data['body_ar'];
        $data['subject_ar']=empty($data['subject_ar'])?$data['subject']:$data['subject_ar'];

        $users =     self::get_users_session($data['recipients_ids']);
        unset($data['recipients_ids']);// its not used

        $insertion_data = array_fill(0, count($users),$data);

        // $insertion_data is array of single data entry each distinguished by user ID:recipient

        // $final_users= array_column($users, 'recipient_id','recipient_id');
        // $insertion_data= array_merge($insertion_data,$final_users);

        // remove and use array merge instead future todo
        foreach ($users as $key => $user){
            $insertion_data[$key]['recipient_id']=$user->recipient_id;
            if ($user->client_lang=='arabic'){
                $data['body']=$data['body_ar'];
                $data['subject']=$data['subject_ar'];
            }

        }


    //    insert_batch to web_notification_alert
        $inserted= self::insert_batch_with_ids_to_notification($insertion_data);

    //    make curl request
        $curl_data = array
        (
            'registration_ids' => array_column($users,'user_token'),
            'notification' => array
            (
                'body' =>$data['body'],
                'title' =>  $data['subject'],
                'sound' => 'default',
                'icon' => Constants::NEJOUM_CDN.'assets/img/notification_bell.png',
                'click_action' => Constants::NEJOUM_CDN.$data['url'],
            ),
            'data' => array
            (
                'sender_id' => $data['sender_id'],
                'url' => Constants::NEJOUM_SYSTEM.$data['url']
            ),
            'priority' => (int)$data['priority']
        );

        self::send_via_curl($curl_data,Constants::FCM_BASE_URL,Constants::FCM_WEB_SECRET_KEY);
        return $inserted;
    }

    public static function send_notification_service_customer($data,$show_popup=true){
        if (self::is_localhost()) return true;
        list($data, $customers, $insertion_data) = self::perpare_date_for_customers($data);
        if ($show_popup&&$insertion_data){
            //self::insert_to_general_notification($insertion_data);
        }
        self::send_push_notification_curl($customers, $data);
        return true;// these are the ids of inserted notifications in db. may be used to link to chat
    }

    public static function send_push_notification_curl($customers, $data){
        $customerList = array_chunk($customers, 999);
        foreach ($customerList as $customers){
        $curl_data = array
        (
            'registration_ids' => array_column($customers, 'user_token'),
            'notification' => array
            (
                'body' => $data['notification_text'],
                'title' => $data['subject'] . ' 📢 ',
                'sound' => 'default',
                'icon' => 'default',
                'click_action' => $data['url'],
            ),
        );
        self::send_via_curl($curl_data, Constants::FCM_BASE_URL, Constants::FCM_MOBILE_ACCESS_KEY);
        }
    }

    public static function insert_to_general_notification($data){
        return DB::insert('general_notification', $data);
    }

    public static function getCustomersTokens($customers){
        if(empty($customers)) return false;

        $customers = !is_array($customers) ? [$customers] : $customers;
        $users = DB::table('user_devices')
            ->select('Device_push_regid as user_token', 'Device_lang', 'customer_id')
            ->whereIn('customer_id', $customers)
            ->where('logged_in', '0')
            ->where('logged_out_from_devices', '0')
            ->where('deleted', '0')
            ->get();

        $webTokens = DB::table('customer_web_tokens')
            ->select('user_token', 'customer_id')
            ->whereIn('customer_id', $customers)
            ->get();

        $result = array_merge($users->toArray(), $webTokens->toArray());
        return $result;
    }

    public static function perpare_date_for_customers($data){
        $data['notification_text_ar'] = empty($data['notification_text_ar']) ? $data['notification_text'] : $data['notification_text_ar'];
        $data['subject_ar'] = empty($data['subject_ar']) ? $data['subject'] : $data['subject_ar'];
        $customers = self::getCustomersTokens($data['customers']);
        unset($data['customers']);// its not used
        $insertion_data = array_fill(0, count($customers), $data);
        $existingInserted = [];
        foreach ($customers as $key => $customer) {
            $insertion_data[$key]['customer_id'] = $customer->customer_id;
            if(in_array($customer->customer_id, $existingInserted)){
            unset($insertion_data[$key]);//remove duplicate notifications in general notification in database
            }else{
                $existingInserted[] = $customer->customer_id;
            }
            if ($customer->client_lang == 'arabic') {
                $data['notification_text'] = $data['notification_text_ar'];
                $data['subject'] = $data['subject_ar'];
            }
        }
        return array($data, $customers, $insertion_data);
    }

    public static function get_users_session($users)
    {
        return DB::table('user_web_sessions')
            ->select(DB::raw('user_token,client_lang,user_id as recipient_id'))
            ->where('has_session', true)
            ->where('is_active', true)
            ->whereIn('user_id', $users)
            ->get()->toArray();
    }
    public static function insert_batch_with_ids_to_notification($data)
    {
        return DB::table('web_notification_alert')->insert($data);
    }
    public static function send_via_curl($data,$url,$secret)
    {
        if(Helpers::is_localhost()) return;

        $headers = array
        (
            'Authorization: key=' . $secret,
            'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_exec($ch);
        curl_close($ch);
    }

    public static function get_users_by_department($id)
    {
        $query = DB::table('user_department')
            ->select(DB::raw('DISTINCT(user_department.user_id) AS id'))
            ->join('users', 'user_department.user_id', '=', 'users.user_id')
            ->where('users.status', '1')
            ->where('user_department.department_id', $id)
            ->where('users.is_deleted', '0');
        return $query->get()->toArray();
    }

    public static function get_users_by_role($id)
    {
        $query = DB::table('users')
            ->select(DB::raw('DISTINCT(users.user_id) AS id'))
            ->where('users.status', '1')
            ->where('users.role_id', $id)
            ->where('users.is_deleted', '0');
        return $query->get()->toArray();
    }


    public static function get_closed_date()
    {
        return DB::table('closed_periods')->selectRaw('MAX(end_date) end_date')->where('approved', '1')->first()->end_date;
    }

    public static function get_closing_journals()
    {
        return DB::table('closed_periods')->selectRaw('closing_journal, customer_balance_journal')->where('approved', '1')->get()->toArray();
    }

    public static function get_db_timestamp()
    {
        return date('Y-m-d H:i:s');
    }

    static function is_localhost(){
        return in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost','nejoum.test']) ? true : false;

    }

    static function getS3Client(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => env('AWS_REGION'),
            'credentials' => [
                'key' => env('AWS_KEY_ID'),
                'secret' => env('AWS_SECRET_KEY'),
            ],
        ]);

    }

    static function uploadToS3($source, $destination, $params=[]){
        if(Helpers::is_localhost()){
            // return false;
        }

        $file_type = $params['file_type'];
        $destination = str_replace('//', '/', $destination);

        $s3 = Helpers::getS3Client();
        $bucket = env('AWS_S3_BUCKET_NAME');

        $result = $s3->upload($bucket, $destination, fopen($source, 'r'), 'private', [
            'region' => env('AWS_REGION'),
            'Key'    => $destination,
            'ContentType' => $file_type,
            'StorageClass' => 'STANDARD_IA',
        ]);

        return $result['ObjectURL'];
    }

    public static function getOrder($orderVar){
        $dir = 'desc';
        if(strpos($orderVar,'-') !== false){
            $dir = 'asc';
            $orderVar = str_replace('-','',$orderVar);
        }
        $orderableColumns = ['carMakerName',
            'lotnumber',
            'auction_location_name',
            'port_name','purchasedate',
            'paymentDate',
            'picked_date',
            'delivered_date',
            'delivered_title',
            'delivered_car_key',
            'loaded_date',
            'booking_number',
            'container_number',
            'etd',
            'shipping_date',
            'eta',
            'destination',
            'total_cars',
            'arrived_port_date',
            'arrived_store_date',
            'total_shipping',
        ];
        if(in_array($orderVar,$orderableColumns)){
            return ['col'=>$orderVar,'dir'=>$dir];
        }
        return false;
    }
    public function autoExtraNote($car_id){
        $auto_extra_notes = "";
        if(self::check_transaction($car_id, 103)){
            $auto_extra = self::get_auto_extra_for_car($car_id);
            foreach ($auto_extra as $keyExtra => $valueExtra) {
                $auto_extra_notes .= '('.$valueExtra->amount.'$) '.$valueExtra->note;
              }
        }
        return $auto_extra_notes;
    }
    public static function check_transaction($car_id, $car_step){
        $query = DB::table('accounttransaction')
		->where('accounttransaction.car_id', $car_id)
		->where('deleted', 0)
        ->where('car_step', $car_step);
        return $query->first()->car_id;
    }
    public static function get_auto_extra_for_car($car_id)
    {
        $query = DB::table('car')
            ->selectRaw('external_car.region_id as region_id_external,auction_location.region_id as region_id , auction_location.country_id ,car.destination as port_id,car.customer_id,
        car.purchasedate as purchase_date,booking_bl_container.arrival_date as arrive_date,arrived_car.delivered_date as purchase_date_for_external,shipping_status.shipping_date,car_model.id_body_type')
            ->leftJoin('auction_location', 'car.auction_location_id', '=', 'auction_location.auction_location_id')
            ->leftJoin('arrived_car', 'car.id', '=', 'arrived_car.car_id')
            ->leftJoin('external_car', 'car.id', '=', 'external_car.car_id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
            ->leftJoin('booking', 'booking.booking_id', '=', 'container_car.booking_id')
            ->leftJoin('booking_bl_container', 'booking.booking_id', '=', 'booking_bl_container.booking_id')
            ->leftJoin('shipping_status', 'booking.booking_id', '=', 'shipping_status.booking_id')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->where('id', $car_id);
        $car_data = $query->first();
        if ($car_data->region_id == '0') {
            $car_data->region_id =  $car_data->region_id_external;
            $car_data->country_id = self::get_country_for_external($car_data->region_id);
        }
        if ($car_data) {
            $query = DB::table('car')
                ->select('*')
                ->join('customer_contract', function($join) {
                    $join->on('customer_contract.customer_id', '=', 'car.customer_id');
                    $join->where('customer_contract.status', '=', '1');
                    $join->where(function ($q) {
                        $q->whereRaw("(car.purchasedate AND customer_contract.start_date <= car.purchasedate AND (customer_contract.end_date >= car.purchasedate  OR  customer_contract.end_date IS NULL))");
                        $q->orWhereRaw("(NULLIF(car.purchasedate, ' ') IS NULL AND customer_contract.start_date <= DATE(car.create_date) AND (customer_contract.end_date >= DATE(car.create_date) OR customer_contract.end_date IS NULL))");
                    });
                })
                ->join('auto_extra_customers', function($join) {
                    $join->on('auto_extra_customers.customer_id', '=', 'customer_contract.customer_id');
                    $join->on('auto_extra_customers.customer_contract_id', '=', 'customer_contract.customer_contract_id');
                })
                ->join('auto_extra', 'auto_extra.id', '=', 'auto_extra_customers.auto_extra_id')
                ->join('auto_extra_route', 'auto_extra.id', '=', 'auto_extra_route.auto_extra_id')
                ->join('auto_extra_port', 'auto_extra_route.id', '=', 'auto_extra_port.route_id')
                ->leftJoin('auto_extra_vehicle', 'auto_extra_vehicle.auto_extra_id', '=', 'auto_extra.id')
                ->where('auto_extra.status', '1')
                ->where('auto_extra.deleted', '0')
                ->where('auto_extra_customers.customer_id', $car_data->customer_id)
                ->where('auto_extra_route.country_id', $car_data->country_id)
                ->where('auto_extra_route.region_id', $car_data->region_id)
                ->where('auto_extra_port.port_id', $car_data->port_id)
                ->where('car.id', $car_id);
            if ($car_data->id_body_type) {
                $id_body_types = explode(',', $car_data->id_body_type);
                $query->where(function ($q) use ($id_body_types) {
                    $q->whereRaw("IF(vtype_id IS NULL, true, auto_extra_vehicle.vtype_id = '" . trim($id_body_types[0]) . "')");
                    foreach ($id_body_types as $key => $id_body_type) {
                        if (!$key) {
                            continue;
                        }
                        $q->orWhereRaw("IF(vtype_id IS NULL, true, auto_extra_vehicle.vtype_id = '" . trim($id_body_type) . "')");
                    }
                });
            } else {
                $query->whereRaw("IF(vtype_id IS NOT NULL, false, true)");
            }

            $query->groupBy(DB::raw('auto_extra.id,auto_extra_vehicle.vtype_id'));
            $result =  $query->get()->toArray();
        }
        $final_result = array();
        //        compare in code
        foreach ($result as $key => $single_result) {
            $dateType = $single_result->date_type;
            switch ($dateType) {
                case 1:
                    if (empty($car_data->purchase_date)) {
                        $car_data->purchase_date = $car_data->purchase_date_for_external;
                    }
                    if ($single_result->start_date <= $car_data->purchase_date && $single_result->end_date >= $car_data->purchase_date) {
                        $final_result[] = $single_result;
                    }
                    break;
                case 2:
                    if ($car_data->port_id == '38' && !$car_data->arrive_date) {
                        if ($single_result->start_date <= $car_data->shipping_date && $single_result->end_date >= $car_data->shipping_date) {
                            $final_result[] = $single_result;
                        }
                    } elseif ($single_result->start_date <= $car_data->arrive_date && $single_result->end_date >= $car_data->arrive_date) {
                        $final_result[] = $single_result;
                    }
                    break;
                case 3:
                    if ($single_result->start_date <=  $car_data->shipping_date && $single_result->end_date >= $car_data->shipping_date) {
                        $final_result[] = $single_result;
                    }
                    break;
            }
        }
        return isset($final_result) ? $final_result : false;
    }

    public static function get_country_for_external($region_id){
        $query = DB::table('auction_location')
            ->select('country_id')
            ->where('region_id',$region_id);
        return $query->first()->country_id;
    }

    public static function formatNotes($note){
        $note = str_replace("'", "", $note);
        $note = preg_replace("/\r|\n/", "", $note);
        $note= str_replace(",", "", $note);
        if (empty($note)){ $note=""; }
        return $note;
    }

    public static function save_general_files($table_name, $column_value, $uploaded_files, $params){
        $table_id = self::get_table_id($table_name);

        foreach($uploaded_files as $file){
            $fileData = [
              'table_id' => $table_id,
              'primary_column' => $column_value,
              'tag' => $params['tag'] ?? null,
              'file_name' => $file['path'],
              'create_by' => 98,
            ];
            DB::table('general_files')->insert($fileData);
        }
    }

    public static function get_table_id($table_name){
        $dbtable = DB::table('tables')
        ->select('id')
        ->where('name',$table_name)
        ->first();

        if(empty($dbtable)){
            DB::table('tables')->insert(['name' => $table_name]);
            $table_id = DB::getPdo()->lastInsertId();
        } else{
            $table_id = $dbtable->id;
        }

        return $table_id;
    }

    public static function replace_general_file($table_name, $column_value, $uploaded_file, $params){
        $table_id = self::get_table_id($table_name);

        DB::table('general_files')->where(['table_id' => $table_id, 'primary_column' => $column_value, 'tag' => $params['tag']])->delete();

        $fileData = [
            'table_id' => $table_id,
            'primary_column' => $column_value,
            'tag' => $params['tag'] ?? null,
            'file_name' => $uploaded_file['path'],
            'create_by' => 98,
        ];
        DB::table('general_files')->insert($fileData);
    }
}
