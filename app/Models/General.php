<?php

namespace App\Models;

use App\Libraries\Helpers;
use Faker\Extension\Helper;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;
use stdClass;
use App\Libraries\Constants;

class General extends Model
{
    protected $table        = null;
    protected $primaryKey   = null;

    public static function getQueryComplaintMessage($args) {
        $customer_id = $args['customer_id'];
        $complaint_status = isset($args['complaint_status']) ? $args['complaint_status'] : null;
        $select = "complaint_message.*, complaint_types.title_en as complaint_type_en, complaint_types.title_ar as complaint_type_ar";

        $query1 = DB::Table('complaint_message')
            ->join('complaint_message as cm', function ($join) {
                $join->on('complaint_message.parent_id', '=', 'cm.complaint_message_id');
            })
            ->where('cm.customer_id', '=', $customer_id)
            ->where('complaint_message.parent_id', '>', '0')
            ->where('complaint_message.show_to_customer', '=', '1')
            ->where('complaint_message.deleted', '=', '0');

        if ($complaint_status !== null && $complaint_status !== '') {
            $query1->where('cm.status', '=', $complaint_status);
        }

        $query1->groupBy('complaint_message.complaint_message_id')
            ->select(DB::raw($select));

        $query2 = DB::Table('complaint_message')
            ->leftJoin('complaint_types', 'complaint_types.id', '=', 'complaint_message.complaint_type')
            ->where('complaint_message.customer_id', '=', $customer_id)
            ->where('complaint_message.parent_id', '=', '0')
            ->where('complaint_message.show_to_customer', '=', '1')
            ->where('complaint_message.deleted', '=', '0');

        if ($complaint_status !== null && $complaint_status !== '') {
            $query2->where('complaint_message.status', '=', $complaint_status);
        }

        $query2->groupBy('complaint_message.complaint_message_id')
            ->select(DB::raw($select));

        return $query2;
    }


    public static function getComplaintMessage($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $query = self::getQueryComplaintMessage($args)
            ->orderBy('create_date', 'desc')
            ->skip($page * $limit)->take($limit);
        return $query->get()->toArray();
    }

    public static function getComplaintMessageCount($args)
    {
        //return '12';
        $query = self::getQueryComplaintMessage($args);
        $query = Helpers::getRawSql($query);
        $query = DB::select(DB::raw("SELECT COUNT(complaint_message_id) as totalRecords from ({$query}) cm"));
        return $query[0]->totalRecords;
    }

    public static function updateGeneralNotification($data, $where){
        return DB::table('general_notification')
        ->where($where)
        ->update($data);
    }


    public static function addContactMessage($data){
        return DB::Table('complaint_message')->insertGetId($data);
    }
    public static function checkIfChatOpen($customer_id, $lot_vin){
      return  DB::table('complaint_message')
            ->where('customer_id', $customer_id)
            ->where('lot_vin', $lot_vin)
            ->where('complaint_type', 40)
            ->whereIn('status', [0,1])
            ->first();
    }
    public static function addChatMessage($data): int
    {
        return DB::Table('complaint_messages_chat')->insertGetId($data);
    }

    public static function getChatMessagesForComplain($args): array
    {
        $complaint_message_id = $args['complaint_message_id'];
        $select = "
            complaint_messages_chat.*,
            GROUP_CONCAT(general_files.file_name) as attachments_files,
            GROUP_CONCAT(general_files.create_date) as attachments_files_create_date
        ";
        $query = DB::Table('complaint_messages_chat')
            ->leftJoin('general_files', function($join) {
                $join->on('complaint_messages_chat.id', '=', 'general_files.primary_column')
                     ->where('general_files.tag', '=', 'complaint_messages_chat');
            })
            ->where('complaint_messages_chat.complaint_message_id', '=', $complaint_message_id)
            ->select(DB::raw($select))
            ->groupBy('complaint_messages_chat.id');
        return $query->get()->toArray();
    }

    public static function getQueryAuctionLocations($args){
        $auction_id = $args['auction_id'];

        $query = DB::table('auction_location')
        ->selectRaw('auction_location.*, IF(auction_location.closed=1, true, false) closed')
        ->where('status', 1)
        ->orderBy('auction_location_name');

        if($args['auction_id']){
            $query->where('auction_location.auction_id', $args['auction_id']);
        }

        if($args['city_id']){
            $query->where('auction_location.city_id', $args['city_id']);
        }

        return $query;
    }
    public static function getAuctionLocations($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        if (!array_intersect_key($args, array_flip(['auction_id', 'state_id', 'city_id']))) {
            return [];
        }

        $query = General::getQueryAuctionLocations($args)
            ->skip($page * $limit)->take($limit);
        return $query->get()->toArray();
    }

    public static function getAuctionLocationsCount($args)
    {
        $query = General::getQueryAuctionLocations($args);
        $query->select(DB::raw('COUNT(auction_location.auction_location_id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getQueryCountryPorts($args){

        $query = DB::table('port')
        ->select('port.*')
        ->where('status', 1);

        if(!empty($args['country_id'])){
            $query->where('country_id', $args['country_id']);
        }

        if(!empty($args['exclude_country_id'])){
            $query->whereNotIn('country_id', $args['exclude_country_id']);
        }

        return $query;
    }

    public static function getCountryPorts($args){
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;


        $query = General::getQueryCountryPorts($args);
        if($limit != 'all'){
            $query->skip($page * $limit)->take($limit);
        }
        return $query->get()->toArray();
    }
    public static function getComplaintMessageDetails($args){
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $complaint_message_id = $args['complaint_message_id'];
        if (empty($complaint_message_id)) {
            return [];
        }
        $query = self::getQueryComplaintMessageDetails($args)
            ->skip($page * $limit)->take($limit);
        return $query->get()->toArray();
    }
    public static function getQueryComplaintMessageDetails($args) {
        $complaint_message_id = $args['complaint_message_id'];
        $select = "complaint_message.*";

        // First query
        $query1 = DB::table('complaint_message')
            ->where([
                ['complaint_message_id', '=', $complaint_message_id],
                ['parent_id', '=', 0],
                ['show_to_customer', '=', 1],
                ['deleted', '=', 0]
            ])
            ->groupBy('complaint_message.complaint_message_id');
        $query1->select(DB::raw($select));

        // Second query
        $query2 = DB::table('complaint_message')
            ->where([
                ['parent_id', '=', $complaint_message_id],
                ['show_to_customer', '=', 1],
                ['deleted', '=', 0]
            ])
            ->groupBy('complaint_message.complaint_message_id')
            ->union($query1);

        $query2->select(DB::raw($select))->orderBy('create_date');;

        return $query2;
    }

    public static function getComplaintMessageDetailsCount($args)
    {
        //return '12';
        $query = self::getQueryComplaintMessageDetails($args);
        $query = Helpers::getRawSql($query);
        $query = DB::select(DB::raw("SELECT COUNT(complaint_message_id) as totalRecords from ({$query}) cm"));
        return $query[0]->totalRecords;
    }

    public static function getCountryPortsCount($args)
    {
        $query = General::getQueryCountryPorts($args);
        $query->select(DB::raw('COUNT(port.port_id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function updateCustomerToken($data){
        return DB::table('customer_web_tokens')
        ->upsert($data,['customer_id'],['user_token']);
    }
}
