<?php

namespace App\Models;

use App\Libraries\Helpers;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\DB;

class Store extends Model
{
    protected $table        = null;
    protected $primaryKey   = null;

    public static function carStorageTransport($car_id)
    {
        $select = "car.*,receive_car.warehouse_id as receive_car_warehouse_id,receive_car.create_date as receive_car_create_date,
        receive_car_warehouse.warehouse_name as receive_car_warehouse_name,final_payment_invoices_details.created_date as final_created_date,
        receive_car.deliver_create_date,DATEDIFF(CURRENT_DATE(), transport_request.received_create_date) as days,transport_request.warehouse_id,transport_request.received_create_date as received_date,
        warehouse_transport.recovery_date,warehouse.warehouse_name, receive_car.skip_storage_after_payment, shipped_car_for_sale_id, shipped_car_for_sale.create_date sell_create_date,
        shipped_car_for_sale.sold_date";

        $finalInvoiceSubQuery = DB::Table('final_payment_invoices_details')
            ->select(DB::raw("MAX(final_payment_invoices_details_id) as row_id, car_id"))
            ->where('car_id', $car_id)
            ->groupBy('car_id');
        $finalInvoiceSubQuery = Helpers::getRawSql($finalInvoiceSubQuery);

        $query = DB::Table('car')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('transport_request', 'transport_request.container_id', '=', 'container_car.container_id')
            ->leftJoin('warehouse_transport', 'warehouse_transport.car_id', '=', 'car.id')
            ->leftJoin('warehouse', 'warehouse.warehouse_id', '=', 'transport_request.warehouse_id')
            ->join('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin('warehouse as receive_car_warehouse', 'receive_car_warehouse.warehouse_id', '=', 'receive_car.warehouse_id')
            ->leftJoin('shipped_car_for_sale', 'shipped_car_for_sale.car_id', '=', 'car.id')
            ->leftJoin(DB::raw(" ($finalInvoiceSubQuery) as final_invoice1 on final_invoice1.car_id = car.id"), function () {
            })
            ->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details_id', '=', 'final_invoice1.row_id')
            ->where('car.deleted', '0')
            ->where('car.cancellation', '0')
            ->where('car.id', $car_id)
            ->whereRaw("IF(receive_car.container_id=0,receive_car.warehouse_id is not null,true)")
            ->groupBy('car.id');

        $query->select(DB::raw($select));
        return (array)$query->first();
    }

    public static function carStorageWarehouse($car_id)
    {
        $finalInvoiceSubQuery = DB::Table('final_payment_invoices_details')
            ->select(DB::raw("MAX(final_payment_invoices_details_id) as row_id, car_id"))
            ->where('car_id', $car_id)
            ->groupBy('car_id');
        $finalInvoiceSubQuery = Helpers::getRawSql($finalInvoiceSubQuery);

        $warehouseTransportSubQuery = DB::Table('warehouse_transport')
            ->select(DB::raw("COUNT(DISTINCT(warehouse_transport.warehouse_transport_id))"))
            ->where('car_id', $car_id);
        $warehouseTransportSubQuery = Helpers::getRawSql($warehouseTransportSubQuery);

        $select = "car.*,receive_car.deliver_create_date,final_payment_invoices_details.created_date as final_created_date,car.id,DATEDIFF(CURRENT_DATE(), warehouse_transport.recovery_date) as days,
        warehouse_transport.to_destination_warehouse_id as warehouse_id,warehouse_transport.recovery_date as received_date,transport_request.received_create_date,warehouse_transport.*,
        warehouse_transport.recovery_date as warehouse_transport_recovery_date,warehouse_transport.from_destination_warehouse_id,warehouse.warehouse_name,($warehouseTransportSubQuery) as carsWarehouseTransportNum";

        $query = DB::Table('warehouse_transport')
            ->join('car', 'car.id', '=', 'warehouse_transport.car_id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('transport_request', 'transport_request.container_id', '=', 'container_car.container_id')
            ->leftJoin('warehouse', 'warehouse.warehouse_id', '=', 'warehouse_transport.to_destination_warehouse_id')
            ->join('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin(DB::raw(" ($finalInvoiceSubQuery) as final_invoice1 on final_invoice1.car_id = car.id"), function () {
            })
            ->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details_id', '=', 'final_invoice1.row_id')
            ->where('car.deleted', '0')
            ->where('car.cancellation', '0')
            ->where('car.id', $car_id)
            ->orderBy('warehouse_transport.recovery_date');

        $query->select(DB::raw($select));
        return collect($query->get())->map(function ($x) {
            return (array) $x;
        })->toArray();
    }

    public static function warehouseRules()
    {
        $select = "store_rule.*,store_rule_per_warehouse.*";

        $query = DB::Table('store_rule')
            ->selectRaw($select)
            ->leftJoin('store_rule_per_warehouse', 'store_rule_per_warehouse.store_rule_id', '=', 'store_rule.store_rule_id');

        return $query->get()->toArray();
    }
}
