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

class CarAccounting extends Model
{
    protected $table        = null;
    protected $primaryKey   = null;

    public static function getCarExtraDetail($args)
    {
        $car_id = $args['car_id'];

        $recoveryTransactionQuery = DB::Table('accounttransaction')
            ->select(DB::raw("SUM(Debit) as recovery_price"))
            ->whereRaw('car_step = 1112 AND deleted = 0 AND car_id = car.id');
        $recoveryTransactionQuery = $recoveryTransactionQuery->toSql();

        $towingFineTransaction = DB::Table('accounttransaction')
            ->select(DB::raw("SUM(Debit) as towing_fine"))
            ->whereRaw('car_step = 20 AND deleted = 0 AND car_id = car.id AND AccountID = customer.account_id');
        $towingFineTransaction = $towingFineTransaction->toSql();

        $select = [
            'additional_extra.extra_price as auto_extra',
            'general_extra.extra as general_extra',
            'general_extra.note as general_extra_note',
            'car_sell_price.sale_vat',
            'car_total_cost.shipping_commission',
            DB::raw("COALESCE( ($towingFineTransaction), 0) as towing_fine"),
            DB::raw("COALESCE( ($recoveryTransactionQuery), 0) as recovery_price")
        ];

        $query = DB::Table('car')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('additional_extra', 'additional_extra.car_id', '=', 'car.id')
            ->leftJoin('general_extra', 'general_extra.car_id', '=', 'car.id')
            ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
            ->leftJoin('warehouse_transport', 'warehouse_transport.car_id', '=', 'car.id')
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->where('car.id', $car_id)
            ->groupBy('car.id');

        $query->select($select);
        return $query->first();
    }

    public static function getPaidCarInvoices($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;

        $carsCountQuery = DB::Table('bill_details')
            ->select(DB::raw("COUNT(bill_details.car_id)"))
            ->whereRaw('bill_details.bill_id = bill.id')
            ->groupBy('bill_details.bill_id');
        $carsCountQuery = $carsCountQuery->toSql();

        $select = [
            DB::raw('@a:=@a+1 index_no'),
            'bill.*',
            'customer.full_name as customer_name',
            'customer.full_name_ar as customer_name_ar',
            'users.full_name as creator_name, ',
            'roles.name as creator_position',
            DB::raw("COALESCE( ($carsCountQuery), 0) as total_cars")
        ];

        $query = DB::Table('bill')
            ->leftJoin('customer', 'customer.customer_id', '=', 'bill.customer_id')
            ->leftJoin('users', 'users.user_id', '=', 'bill.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->join(DB::raw('(SELECT @a:= ' . $page * $limit . ') as a'), function () {
            })
            ->where('bill.inv_type', 1)
            ->skip($page * $limit)->take($limit);

        if (!empty($args['bill_id'])) {
            $query->where('bill.id', $args['bill_id']);
        }

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if (!empty($args['lotnumber'])) {
            $query->leftJoin('bill_details', 'bill_details.bill_id', '=', 'bill.id');
            $query->leftJoin('car', 'car.id', '=', 'bill_details.car_id');
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("bill.create_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("bill.create_date", '<=', $args['date_to']);
        }

        $query->select($select);
        return $query->get()->toArray();
    }

    public static function getPaidCarInvoiceDetail($args)
    {
        if (empty($args['bill_id'])) {
            return [];
        }

        $select = 'bill_details.*, car.* , car_make.name AS carMakerName , color.color_code,buyer.buyer_number,
        car_model.name AS carModelName, vehicle_type.name AS vehicleName,auction.title AS auction_title, car_sell_price.*,
        auction_location.auction_location_name,port.port_name, customer.full_name AS customer_name,customer.full_name_ar AS customer_name_ar,
        customer.membership_id, customer.customer_type, bill.inv_file AS invoice_file,bill_details.amount_pay as bill_details_paid_amount,bill_details.remaining_amount as bill_details_remaining_amount,
        bill.Inv_no,bill.inv_type as invoice_type, bill.total_amount as bill_total_amount,bill.total_required bill_total_required,bill.remaining_amount as bill_remaining_amount,
        bill.create_date, bill_details.tax_canada as bill_details_tax_canada';

        $query = DB::Table('bill_details')
            ->leftJoin('bill', 'bill.id', '=', 'bill_details.bill_id')
            ->leftJoin('car', 'car.id', '=', 'bill_details.car_id')
            ->leftJoin('color', 'color.color_id', '=', 'car.color')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('buyer', 'buyer.buyer_id', '=', 'car.buyer_id')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('customer', 'customer.customer_id', '=', 'bill.customer_id')
            ->leftJoin('users', 'users.user_id', '=', 'bill.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('bill_details.bill_id', $args['bill_id']);

        if (!empty($args['car_id'])) {
            $query->where('bill_details.car_id', $args['car_id']);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getQueryCancelledCarInvoices($args)
    {

        $query = DB::Table('bill')
            ->leftJoin('customer', 'customer.customer_id', '=', 'bill.customer_id')
            ->leftJoin('users', 'users.user_id', '=', 'bill.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('bill.inv_type', 2)
            ->where('bill.total_amount', '>', 0);

        if (!empty($args['bill_id'])) {
            $query->where('bill.id', $args['bill_id']);
        }

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if (!empty($args['lotnumber'])) {
            $query->leftJoin('bill_details', 'bill_details.bill_id', '=', 'bill.id');
            $query->leftJoin('car', 'car.id', '=', 'bill_details.car_id');
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("bill.create_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("bill.create_date", '<=', $args['date_to']);
        }

        return $query;
    }

    public static function getCancelledCarInvoices($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;

        $carsCountQuery = DB::Table('bill_details')
            ->select(DB::raw("COUNT(bill_details.car_id)"))
            ->whereRaw('bill_details.bill_id = bill.id')
            ->groupBy('bill_details.bill_id');
        $carsCountQuery = $carsCountQuery->toSql();

        $select = [
            DB::raw('@a:=@a+1 index_no'),
            'bill.*',
            'customer.full_name as customer_name',
            'customer.full_name_ar as customer_name_ar',
            'users.full_name as creator_name, ',
            'roles.name as creator_position',
            DB::raw("COALESCE( ($carsCountQuery), 0) as total_cars")
        ];

        $query = CarAccounting::getQueryCancelledCarInvoices($args);
        $query->join(DB::raw('(SELECT @a:= ' . $page * $limit . ') as a'), function () {
        })
            ->skip($page * $limit)->take($limit)
            ->having('total_cars', '>', 0);

        $query->select($select);
        return $query->get()->toArray();
    }

    public static function getCancelledCarInvoicesCount($args)
    {
        $query = CarAccounting::getQueryCancelledCarInvoices($args);
        $query->select(DB::raw('COUNT(bill.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getQueryPaidByCustomerInvoices($args)
    {
        $query = DB::Table('bill_details')
            ->leftJoin('car', 'car.id', '=', 'bill_details.car_id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('users', 'users.user_id', '=', 'bill_details.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('bill_details.bill_id', 0)
            ->orderBy('bill_details.create_date', 'desc');

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if (!empty($args['lotnumber'])) {
            $query->leftJoin('car', 'car.id', '=', 'bill_details.car_id');
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("bill_details.create_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("bill_details.create_date", '<=', $args['date_to']);
        }
        return $query;
    }

    public static function getPaidByCustomerInvoices($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;

        $select = [
            DB::raw('@a:=@a+1 index_no'),
            'bill_details.*',
            'car.*',
            'customer.full_name as customer_name',
            'customer.full_name_ar as customer_name_ar',
            'users.full_name as creator_name, ',
            'roles.name as creator_position',
        ];

        $query = CarAccounting::getQueryPaidByCustomerInvoices($args);
        $query->join(DB::raw('(SELECT @a:= ' . $page * $limit . ') as a'), function () {
        })
            ->skip($page * $limit)->take($limit);

        $query->select($select);
        return $query->get()->toArray();
    }

    public static function getPaidByCustomerInvoicesCount($args)
    {
        $query = CarAccounting::getQueryPaidByCustomerInvoices($args);
        $query->select(DB::raw('COUNT(bill_details.bill_id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getQueryFinalInvoices($args)
    {

        $query = DB::Table('final_payment_invoices')
            ->leftJoin('customer', 'customer.customer_id', '=', 'final_payment_invoices.customer_id')
            ->leftJoin('users', 'users.user_id', '=', 'final_payment_invoices.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id');

        if (!empty($args['invoice_id'])) {
            $query->where('final_payment_invoices.final_payment_invoices_id', $args['invoice_id']);
        }

        if (!empty($args['customer_id'])) {
            $query->where('customer.customer_id', $args['customer_id']);
        }

        if (!empty($args['lotnumber'])) {
            $query->leftJoin('final_payment_invoices_details', 'final_payment_invoices_details.final_payment_invoices_id', '=', 'final_payment_invoices.final_payment_invoices_id');
            $query->leftJoin('car', 'car.id', '=', 'final_payment_invoices_details.car_id');
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("final_payment_invoices.create_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("final_payment_invoices.create_date", '<=', $args['date_to']);
        }

        return $query;
    }

    public static function getFinalInvoices($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;

        $select = [
            DB::raw('@a:=@a+1 index_no'),
            'final_payment_invoices.*',
            'customer.full_name as customer_name',
            'customer.full_name_ar as customer_name_ar',
            'users.full_name as creator_name, ',
            'roles.name as creator_position',
        ];

        $query = CarAccounting::getQueryFinalInvoices($args);
        $query->join(DB::raw('(SELECT @a:= ' . $page * $limit . ') as a'), function () {
        })
            ->skip($page * $limit)->take($limit);

        $query->select($select);
        return $query->get()->toArray();
    }

    public static function getFinalInvoicesCount($args)
    {
        $query = CarAccounting::getQueryFinalInvoices($args);
        $query->select(DB::raw('COUNT(final_payment_invoices.final_payment_invoices_id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getFinalInvoiceDetail($args)
    {
        if (empty($args['invoice_id'])) {
            return [];
        }

        $select = "final_payment_invoices_details.*,final_payment_invoices.invoice_numer as final_invoice_number,car.* , 
        car_make.name AS carMakerName , car_model.name AS carModelName ,vehicle_type.name as vehicleName,
        final_payment_invoices_details.remaining_amount, users.full_name as creator_name, roles.position as creator_position";

        $query = DB::Table('final_payment_invoices_details')
            ->leftJoin('final_payment_invoices', 'final_payment_invoices.final_payment_invoices_id', '=', 'final_payment_invoices_details.final_payment_invoices_id')
            ->leftJoin('car', 'car.id', '=', 'final_payment_invoices_details.car_id')
            ->leftJoin('color', 'color.color_id', '=', 'car.color')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('users', 'users.user_id', '=', 'final_payment_invoices.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('final_payment_invoices_details.final_payment_invoices_id', $args['invoice_id']);

        if (!empty($args['car_id'])) {
            $query->where('final_payment_invoices_details.car_id', $args['car_id']);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCarFinalInvoiceDetail($args)
    {
        if (empty($args['invoice_id'])) {
            return [];
        }

        $previousBalanceQuery = DB::Table('final_payment_invoices_details as invoice_detail2')
            ->select(DB::raw("SUM(invoice_detail2.amount_paid)"))
            ->whereRaw("invoice_detail2.car_id = final_payment_invoices_details.car_id");
        $previousBalanceQuery = $previousBalanceQuery->toSql();

        $select = "final_payment_invoices_details.*,final_payment_invoices.invoice_numer as final_invoice_number,car.* , car_make.name AS carMakerName , car_model.name AS carModelName,
        final_payment_invoices_details.remaining_amount AS remaining_amount,auction.title,auction_location.auction_location_name,region.short_name, car_sell_price.sale_vat as sale_vat,car_sell_price.car_custom_sell as custom,
        users.full_name AS creator_name, roles.position as creator_position, final_payment_invoices_details.bos as bos, buyer.buyer_number,clearance_extra.extra_sale_acc As clearance_extra,
        transportation_extra.extra_sale_price AS transportation_extra,
        shipping_extra.extra_sale_acc AS shipping_extra,shipping_extra.extra_sale_acc AS shipping_extra_sale_price,
        loading_extra.extra_sale_acc AS loading_extra,post_extra.extra_sale_acc AS post_extra,
        post_extra.extra_sale_price AS post_extra_sale_price,general_extra.extra as general_extra,
        car_total_cost.shipping_commission,car_total_cost.recovery,final_payment_invoices_details.amount_paid as amount_paid,
        towing_fines_invoices_details.cu_fine_amount_dallor,towing_fines_invoices_details.currency_rate,final_bill.storage_fine, SUM(final_bill.storage_fine + final_bill.amount_required) as total_required_amount,final_payment_invoices_details.amount_paid,
        customer.full_name AS customer_name,customer.full_name_ar AS customer_name_ar, customer.membership_id, customer.customer_type,
        ($previousBalanceQuery) - final_payment_invoices_details.amount_paid as previous_amount_paid, final_payment_invoices.create_date as final_invoice_date";

        $query = DB::Table('final_payment_invoices_details')
            ->leftJoin('final_payment_invoices', 'final_payment_invoices.final_payment_invoices_id', '=', 'final_payment_invoices_details.final_payment_invoices_id')
            ->leftJoin('car', 'car.id', '=', 'final_payment_invoices_details.car_id')
            ->leftJoin('color', 'color.color_id', '=', 'car.color')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('buyer', 'buyer.buyer_id', '=', 'car.buyer_id')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('region', 'region.region_id', '=', 'auction_location.region_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('customer', 'customer.customer_id', '=', 'final_payment_invoices.customer_id')
            ->leftJoin('clearance_extra', 'clearance_extra.car_id', '=', 'car.id')
            ->leftJoin('transportation_extra', 'transportation_extra.car_id', '=', 'car.id')
            ->leftJoin('shipping_extra', 'shipping_extra.car_id', '=', 'car.id')
            ->leftJoin('loading_extra', 'loading_extra.car_id', '=', 'car.id')
            ->leftJoin('post_extra', 'post_extra.car_id', '=', 'car.id')
            ->leftJoin('general_extra', 'general_extra.car_id', '=', 'car.id')
            ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
            ->leftJoin('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->leftJoin('final_bill', 'final_bill.car_id', '=', 'car.id')
            ->leftJoin('towing_fines_invoices_details', 'towing_fines_invoices_details.car_id', '=', 'car.id')
            ->leftJoin('users', 'users.user_id', '=', 'final_payment_invoices.create_by')
            ->leftJoin('roles', 'roles.role_id', '=', 'users.role_id')
            ->where('final_payment_invoices_details.final_payment_invoices_id', $args['invoice_id'])
            ->orderBy('final_payment_invoices_details.created_date')
            ->groupBy('final_payment_invoices_details.car_id');

        if (!empty($args['car_id'])) {
            $query->where('final_payment_invoices_details.car_id', $args['car_id']);
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCarAccountTransactions($args)
    {
        $car_id = $args['car_id'];
        $account_id = $args['account_id'];

        if (empty($car_id) || empty($account_id)) {
            return [];
        }

        $transactionTable = DB::raw("((SELECT * FROM accounttransaction) UNION ALL (SELECT * FROM accounttransaction_closing_1)) as accounttransaction");
        
        // to prevent paramters binding issue : get Raw Sql
        $totalCarCostQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Debit)"))
            ->where('deleted', 0)
            ->where('car_step', 1)
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $totalCarCostQuery = Helpers::getRawSql($totalCarCostQuery);

        $totalCarCostPaidQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Credit)"))
            ->where('deleted', 0)
            ->where('car_step', 1)
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $totalCarCostPaidQuery = Helpers::getRawSql($totalCarCostPaidQuery);

        $totalClearanceQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Debit)"))
            ->where('deleted', '0')
            ->whereIn('car_step', [8, 10])
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $totalClearanceQuery = Helpers::getRawSql($totalClearanceQuery);

        $clearanceDiscountQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Credit)"))
            ->where('deleted', '0')
            ->where('car_step', 108)
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $clearanceDiscountQuery = Helpers::getRawSql($clearanceDiscountQuery);

        $totalCarShippingQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Debit)"))
            ->where('deleted', '0')
            ->whereIn('car_step', [5, 6, 102, 103, 22222])
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $totalCarShippingQuery = Helpers::getRawSql($totalCarShippingQuery);

        $finalBillQuery = DB::Table('final_bill')
            ->select("amount_required")
            ->where('final_bill.car_id', $car_id);
        $finalBillQuery = Helpers::getRawSql($finalBillQuery);

        $totalStorageQuery = DB::Table($transactionTable)
            ->select(DB::raw("SUM(accounttransaction.Debit)"))
            ->where('deleted', '0')
            ->where('car_step', 20)
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id);
        $totalStorageQuery = Helpers::getRawSql($totalStorageQuery);

        $select = "accounttransaction.*, account_home.AccountName AS AccountName,
        ($totalCarCostQuery) as carCost, ($totalCarCostPaidQuery) as carCostPaid, ($totalCarShippingQuery) as totalShipping,
        ($totalClearanceQuery) as totalClearance, ($clearanceDiscountQuery) as clearanceDiscount,
        ($totalStorageQuery) as totalStorage, ($finalBillQuery) as amount_required, car_step.name_en car_step_name_en, car_step.name_ar car_step_name_ar";

        $query = DB::Table($transactionTable)
            ->join('account_home', 'account_home.ID', '=', 'accounttransaction.AccountID')
            ->leftJoin('car_step', function ($join) {
                $join->on('car_step.step', '=', 'accounttransaction.car_step');
                $join->where('car_step.deleted', '=', '0');
            })
            ->where('accounttransaction.deleted', '0')
            ->where('car_id', $car_id)
            ->where('AccountID', $account_id)
            ->where(function ($query) {
                $query->whereNotIn('car_step', [5, 6, 8, 10, 20, 102, 103, 108, 22222, 170, 171, 173, 174, 1998, 1999]);
            })
            ->orderBy('Journal_id');

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function storageFinePerDay($args)
    {
        $car_id = $args['car_id'];

        if (empty($car_id)) {
            return [];
        }

        $query = DB::Table('bill_fines_days')
            ->select('*')
            ->where('car_id', $car_id);

        return $query->get()->toArray();
    }

    public static function getCarAccountingNotes($args)
    {
        $car_id = $args['car_id'];

        if (empty($car_id)) {
            return [];
        }

        $select = "post_extra.note as posted_notes, container_car.noteHere as loading_notes, loading_extra.note as loading2_notes,
        shipping_extra.note as shipping_notes, vat.note as vat_notes, clearance_extra.note as clearance_notes, transportation_extra.note as transportation_notes, 
        discount.note as discount_notes, general_extra.note as generalextra_notes";

        $query = DB::Table('car')
            ->select(DB::raw($select))
            ->leftJoin('post_extra', 'post_extra.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('shipping_extra', 'shipping_extra.car_id', '=', 'car.id')
            ->leftJoin('clearance_extra', 'clearance_extra.car_id', '=', 'car.id')
            ->leftJoin('loading_extra', 'loading_extra.car_id', '=', 'car.id')
            ->leftJoin('transportation_extra', 'transportation_extra.car_id', '=', 'car.id')
            ->leftJoin('discount', 'discount.car_id', '=', 'car.id')
            ->leftJoin('general_extra', 'general_extra.car_id', '=', 'car.id')
            ->leftJoin('vat', 'vat.car_id', '=', 'car.id')
            ->where('car.id', $car_id);

        return (array)$query->get()->first();
    }

    public static function getTaxCanadaComission()
    {
        $query = DB::Table('tax_canada_commission')
            ->select(['id', 'amount'])
            ->where('deleted', 0)
            ->where('status', 1);

        return $query->get()->toArray();
    }

    public static function getSpecialScenarioPorts()
    {
        $query = DB::Table('special_scenario_ports')
            ->select(['port_id'])
            ->where('deleted', 0)
            ->where('status', 1);

        return $query->get()->toArray();
    }

    public static function getQueryUnpaidCars($args)
    {
        $customer_id = $args['customer_id'];
        $today = date('Y-m-d');

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('car_note', 'car_note.car_id', '=', 'car.id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->leftJoin('customer_contract', function($join) use ($today) {
                $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
                $join->where('customer_contract.status', '=', '1');
                $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
                ->where(function ($q) use ($today){
                    $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
                  });
            })
            ->leftJoin('customer_auction_rates', function($join) {
                $join->on('customer_auction_rates.auction_id', '=', 'auction.id');
                $join->on('customer_auction_rates.customer_id', '=', 'customer.customer_id');
                $join->on('customer_auction_rates.contract_id', '=', 'customer_contract.customer_contract_id');
            })
            ->where('car.customer_id', $customer_id)
            ->where('car.car_payment_to_cashier', '0')
            ->where('car.cancellation', '0')
            ->where('car.external_car', '0')
            ->where('car.deleted', '0')
            ->orderBy('car.create_date', 'ASC');

        if (!empty($args['onlinePayment'])) {
            $query->leftJoin('online_payment_cars', 'online_payment_cars.car_id', '=', 'car.id');
            $query->leftJoin('online_payment', 'online_payment.id', '=', 'online_payment_cars.online_payment_id');
            $query->where(function ($q){
                $q->whereNull('online_payment_cars.car_id');
                $q->orWhere('online_payment_cars.status', '2');
                $q->orWhere('online_payment_cars.deleted', '1');
                $q->orWhere('online_payment.status', '2');
                $q->orWhere('online_payment.deleted', '1');
            });
        }

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        return $query;
    }

    public static function getUnpaidCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = "car.* , car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName,
            auction.us_dollar_rate,auction.candian_dollar_rate,auction.title AS auction_title, auction_location.country_id, auction_location.auction_location_name,port.port_name,car_note.notes, customer.full_name AS CustomerName,customer.account_id as account_id, customer_group.id AS group_id,
            customer_auction_rates.usd_rate contract_usd_rate, customer_auction_rates.transfer_fee";

        $query = CarAccounting::getQueryUnpaidCars($args)
            ->groupBy('car.id')
            ->skip($page * $limit)->take($limit);

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getUnpaidCarsCount($args)
    {
        $query = CarAccounting::getQueryUnpaidCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getAuctionLocationFines($args)
    {
        $query = DB::Table('auction_location_fines')
            ->join('auction_fines', 'auction_location_fines.auction_fines_id', '=', 'auction_fines.ID')
            ->select('*');

        if (!empty($args['auction_location_ids'])) {
            $query->whereIn('auction_location_id', $args['auction_location_ids']);
        }

        return $query->get()->toArray();
    }

    public static function getQueryArrivedCars($args)
    {
        $customer_id = $args['customer_id'];

        $shippingPaymentDateSql = DB::Table('accounttransaction')
        ->selectRaw('MAX(DateOfTransAction)')
        ->limit(1)
        ->where('car_step', '0')
        ->where('deleted', '0')
        ->where('type_transaction', '1')
        ->whereRaw('AccountID = customer.account_id')
        ->whereRaw('car_id = car.id');
        $shippingPaymentDateSql = Helpers::getRawSql($shippingPaymentDateSql);

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->leftJoin('warehouse_transport', 'warehouse_transport.car_id', '=', 'car.id')
            ->leftJoin('final_bill', 'final_bill.car_id', '=', 'car.id')
            ->leftJoin('general_extra', 'general_extra.car_id', '=', 'car.id')
            ->join('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
            ->leftJoin('car_customs', 'car_customs.car_id', '=', 'car.id')
            ->leftJoin('additional_extra', 'additional_extra.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('car_storage_fine', 'car_storage_fine.car_id', '=', 'final_bill.car_id')
            ->leftJoin('customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->where('car.customer_id', $customer_id)
            ->where('car.cancellation', '0')
            ->where('car.deleted', '0')
            ->where(function ($query) use ($shippingPaymentDateSql) {
                $query->where('car.final_payment_status', '0');
                $query->orWhere('receive_car.deliver_status', '0');
                $query->orWhereRaw("IF(receive_car.deliver_status = 1, DATE(deliver_create_date) > ($shippingPaymentDateSql), false)");
            })
            ->orderBy('container.container_number', 'desc');

        foreach (Helpers::getSpecialScenarioPorts() as $key => $value) {
            $query->whereRaw("IF(car.destination=$value, container.completed >= 2 or container_car.car_id is null,true)");
        }

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("car_total_cost.create_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("car_total_cost.create_date", '<=', $args['date_to']);
        }

        if (!empty($args['onlinePayment'])) {
            $query->leftJoin('online_payment_cars', 'online_payment_cars.car_id', '=', 'car.id');
            $query->leftJoin('online_payment', 'online_payment.id', '=', 'online_payment_cars.online_payment_id');
            $query->where(function ($q){
                $q->whereNull('online_payment_cars.car_id');
                $q->orWhere('online_payment_cars.status', '2');
                $q->orWhere('online_payment_cars.deleted', '1');
                $q->orWhere('online_payment.status', '2');
                $q->orWhere('online_payment.deleted', '1');
            });
        }

        return $query;
    }

    public static function getArrivedCars($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 50;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $additionalExtraQuery = DB::Table('additional_extra')
            ->selectRaw('SUM(extra_price)')
            ->whereRaw('car_id = car.id');
        $additionalExtraQuery = Helpers::getRawSql($additionalExtraQuery);

        $recoveryTransactionQuery = DB::Table('accounttransaction')
            ->select(DB::raw("SUM(Debit) as recovery_price"))
            ->whereRaw('car_step = 1112 AND deleted = 0 AND car_id = car.id');
        $recoveryTransactionQuery = $recoveryTransactionQuery->toSql();

        $towingFineTransaction = DB::Table('accounttransaction')
            ->select(DB::raw("SUM(Debit) as towing_fine"))
            ->whereRaw('car_step = 20 AND deleted = 0 AND car_id = car.id AND AccountID = customer.account_id');
        $towingFineTransaction = $towingFineTransaction->toSql();
        
        $select = "car.* , car.id as carID,car_make.name AS carMakerName , car_model.name AS carModelName,car_sell_price.*,
        vehicle_type.name AS vehicleName,car_total_cost.total_price as car_total_price,container.container_id,
        auction.title AS auction_title, auction_location.auction_location_name,port.port_name,container.container_number,container.completed,
        warehouse_transport.recovery_price, customer.full_name_ar AS customer_name_ar, ($additionalExtraQuery) as autoExtra2,additional_extra.extra_note as autoExtranote2,
        customer.full_name AS customer_name,customer_group.title as customer_group,car_total_cost.shipping_commission,
        final_bill.amount_required,final_bill.remaining_amount,final_bill.amount_paid,final_bill.vatValue as vatEnable, car_storage_fine.amount as storage,
        general_extra.extra as general_extra_value,general_extra.note as general_extra_note,COALESCE( ($recoveryTransactionQuery), 0) as recovery_price,COALESCE( ($towingFineTransaction), 0) as towing_fine";
        $query = CarAccounting::getQueryArrivedCars($args)
            ->groupBy('car.id')
            ->skip($page * $limit)->take($limit);

        $query->select(DB::raw($select));
        return collect($query->get())->map(function ($x) {
            return (array) $x;
        })->toArray();
    }

    // not used now: using getArrivedCars() to count total cars
    public static function getArrivedCarsCount($args)
    {
        $customer_id = $args['customer_id'];
        $account_id = HelperS::get_customer_account_id($customer_id);

        $accountTransactionQuery = DB::Table('accounttransaction')
            ->selectRaw('car_id')
            ->limit(1)
            ->whereRaw('car_id = car.id');
        $accountTransactionQuery = Helpers::getRawSql($accountTransactionQuery);

        $shippingPaymentDateSql = DB::Table('accounttransaction')
        ->selectRaw('MAX(DateOfTransAction)')
        ->limit(1)
        ->where('car_step', '0')
        ->where('deleted', '0')
        ->where('type_transaction', '1')
        ->where('AccountID', $account_id)
        ->whereRaw('car_id = car.id');
        $shippingPaymentDateSql = Helpers::getRawSql($shippingPaymentDateSql);

        $query = DB::Table('car')
            ->select(DB::raw("COUNT(DISTINCT(car.id)) as totalRecords, ($accountTransactionQuery) as hasTransaction"))
            ->leftJoin('receive_car', 'receive_car.car_id', '=', 'car.id')
            ->join('car_total_cost', 'car_total_cost.car_id', '=', 'car.id')
            ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
            ->leftJoin('container', 'container.container_id', '=', 'container_car.container_id')
            ->where('car.customer_id', $customer_id)
            ->where('car.cancellation', '0')
            ->where('car.deleted', '0')
            ->where(function ($query) use ($shippingPaymentDateSql){
                $query->where('car.final_payment_status', '0');
                $query->orWhere('receive_car.deliver_status', '0');
                $query->orWhereRaw("IF(receive_car.deliver_status = 1, DATE(deliver_create_date) > ($shippingPaymentDateSql), false)");
            })
            ->havingRaw('hasTransaction IS NOT NULL')
            ->orderBy('container.container_number', 'desc');

        foreach (Helpers::getSpecialScenarioPorts() as $key => $value) {
            $query->whereRaw("IF(car.destination=$value, container.completed >= 2 or container_car.car_id is null,true)");
        }

        return $query->first()->totalRecords;
    }

    public static function getBalanceOfTransferredCars($args)
    {
        $customer_id = $args['customer_id'];
        $customer_account_id = $args['customer_account_id'];
        if (empty($customer_id) || empty($customer_account_id)) {
            return [];
        }

        $accountTransactionQuery = DB::Table('car')
            ->selectRaw("car.id, SUM(debit) debit, SUM(credit) credit")
            ->join('accounttransaction as at_car_price', 'at_car_price.car_id', '=', 'car.id')
            ->where('at_car_price.deleted', '0')
            ->where('at_car_price.car_step', '1')
            ->where('at_car_price.AccountID', $customer_account_id)
            ->whereRaw('car_id = car.id')
            ->groupBy('car.id');
        $accountTransactionQuery = Helpers::getRawSql($accountTransactionQuery);

        $carPriceTransactionQuery = DB::Table('car')
            ->selectRaw("car.id, SUM(debit) debit")
            ->join('accounttransaction as at_car_price', 'at_car_price.car_id', '=', 'car.id')
            ->where('at_car_price.deleted', '0')
            ->where('at_car_price.car_step', '1')
            ->where('at_car_price.type_transaction', '3')
            ->whereRaw('at_car_price.Debit > 0')
            ->where('at_car_price.AccountID', $customer_account_id)
            ->whereRaw('car_id = car.id')
            ->groupBy('car.id');
        $carPriceTransactionQuery = Helpers::getRawSql($carPriceTransactionQuery);

        $receivedAmountTransactionQuery = DB::Table('car')
            ->selectRaw("car.id, SUM(credit) credit")
            ->join('accounttransaction as at_received_amount', 'at_received_amount.car_id', '=', 'car.id')
            ->where('at_received_amount.deleted', '0')
            ->where('at_received_amount.car_step', '1')
            ->where('at_received_amount.type_transaction', '1')
            ->where('at_received_amount.AccountID', $customer_account_id)
            ->whereRaw('car_id = car.id')
            ->groupBy('car.id');
        $receivedAmountTransactionQuery = Helpers::getRawSql($receivedAmountTransactionQuery);

        $select = "car.id, car.lotnumber , car.vin , car.year , car.photo , car.purchasedate , SUM(car_transfer_total.credit) totalCredit, car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, 
        auction.title AS auction_title, auction_location.auction_location_name,customer.full_name AS CustomerName,customer.account_id,
        sum(DISTINCT(received_amount.credit)) receivedAmount, sum(DISTINCT(car_price.debit)) carPrice, car.cancellation,
        IF(COALESCE(sum(DISTINCT(car_price.debit)),0) - COALESCE(sum(DISTINCT(received_amount.credit)),0) > 0 AND car.cancellation = 0 , COALESCE(SUM(DISTINCT(car_transfer_total.debit)),0) + COALESCE(bill_details.fine_total_cost_aed,0) + COALESCE(bill_details.late_payment_aed,0), COALESCE(SUM(car_transfer_total.debit),0)) totalDebit";

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('bill_details', 'bill_details.car_id', '=', 'car.id')
            ->leftJoin('final_bill', 'final_bill.car_id', '=', 'car.id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin(DB::raw("($accountTransactionQuery) as car_transfer_total"), function ($join) {
                $join->on('car_transfer_total.id', '=', 'car.id');
            })
            ->leftJoin(DB::raw("($carPriceTransactionQuery) as car_price"), function ($join) {
                $join->on('car_price.id', '=', 'car.id');
            })
            ->leftJoin(DB::raw("($receivedAmountTransactionQuery) as received_amount"), function ($join) {
                $join->on('received_amount.id', '=', 'car.id');
            })
            ->where('car.customer_id', $customer_id)
            ->where('car.deleted', '0')
            ->whereNull('final_bill.car_id')
            ->havingRaw('totalDebit - SUM(car_transfer_total.credit) > 0')
            ->groupBy('car.id');

        if (!empty($args['onlinePayment'])) {
            $query->leftJoin('online_payment_cars', 'online_payment_cars.car_id', '=', 'car.id');
            $query->leftJoin('online_payment', 'online_payment.id', '=', 'online_payment_cars.online_payment_id');
            $query->where(function ($q){
                $q->whereNull('online_payment_cars.car_id');
                $q->orWhere('online_payment_cars.status', '2');
                $q->orWhere('online_payment_cars.deleted', '1');
                $q->orWhere('online_payment.status', '2');
                $q->orWhere('online_payment.deleted', '1');
            });
        }

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCancelledCarsCount($args)
    {
        $query = CarAccounting::getQueryCancelledCars($args);
        $query->select(DB::raw('COUNT(car.id) as totalRecords'));
        return $query->first()->totalRecords;
    }
    public static function getQueryCancelledCars($args)
    {
        $customer_id = $args['customer_id'];
        $today = date('Y-m-d');

        $query = DB::Table('car')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->leftJoin('color', 'car.color','=','color.color_id')
            ->leftJoin('vehicle_type', 'vehicle_type.id_vehicle_type', '=', 'car.id_vehicle_type')
            ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
            ->leftJoin('auction_location as al', 'al.auction_location_id', '=', 'car.auction_location_id')
            ->leftJoin('port', 'port.port_id', '=', 'car.destination')
            ->leftJoin('car_note', 'car_note.car_id', '=', 'car.id')
            ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
            ->leftJoin('customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
            ->leftJoin('auction_location_fines as alf', 'alf.auction_location_id','=','car.auction_location_id')
            ->leftJoin('auction_fines as af', 'af.id', '=' ,'alf.auction_fines_id')
            ->leftJoin('customer_contract', function($join) use ($today) {
                $join->on('customer_contract.customer_id', '=', 'customer.customer_id');
                $join->where('customer_contract.status', '=', '1');
                $join->on('customer_contract.start_date', '<=', DB::raw("'$today'"))
                ->where(function ($q) use ($today){
                    $q->whereNull('customer_contract.end_date')->orWhere('customer_contract.end_date', '>=', $today);
                  });
            })
            ->leftJoin('customer_auction_rates', function($join) {
                $join->on('customer_auction_rates.auction_id', '=', 'auction.id');
                $join->on('customer_auction_rates.customer_id', '=', 'customer.customer_id');
                $join->on('customer_auction_rates.contract_id', '=', 'customer_contract.customer_contract_id');
            })
            ->where('car.customer_id', $customer_id)
            ->where('car.car_payment_to_cashier', '0')
            ->where('car.cancellation', '1')
            ->where('car.external_car', '0')
            ->where('car.deleted', '0')
            ->orderBy('car.create_date', 'desc');

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['onlinePayment'])) {
            $query->leftJoin('online_payment_cars', 'online_payment_cars.car_id', '=', 'car.id');
            $query->leftJoin('online_payment', 'online_payment.id', '=', 'online_payment_cars.online_payment_id');
            $query->where(function ($q){
                $q->whereNull('online_payment_cars.car_id');
                $q->orWhere('online_payment_cars.status', '2');
                $q->orWhere('online_payment_cars.deleted', '1');
                $q->orWhere('online_payment.status', '2');
                $q->orWhere('online_payment.deleted', '1');
            });
        }

        return $query;
    }
    public static function getCancelledCars($args)
    {
        
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;
        $customer_id = $args['customer_id'];
        if (empty($customer_id)) {
            return [];
        }

        $select = 'car.id, car.lotnumber ,car.sales_price , car.vin ,car.year , car.photo, car.purchasedate, car_make.name AS carMakerName , car_model.name AS carModelName, vehicle_type.name AS vehicleName, auction.us_dollar_rate,
        auction.candian_dollar_rate,auction.title AS auction_title, al.auction_location_name, al.country_id, af.day_of_cancellation,
        af.amount_cancellation, af.min_cancellation, af.max_cancellation, customer.full_name AS CustomerName, customer_auction_rates.usd_rate contract_usd_rate, customer_auction_rates.transfer_fee';

        $query = CarAccounting::getQueryCancelledCars($args)
            ->groupBy('car.id')
            ->skip($page * $limit)->take($limit);

        $query->select(DB::raw($select));

        return $query->get()->toArray();
    }

    public static function getShippingCalculatorCars($args)
    {
        if(empty($args['customer_id'])){
            return [];
        }
        $customer_id = $args['customer_id'];

        $select = "car.* ,car_sell_price.towing_cost,car_sell_price.loading_cost,car_sell_price.shipping_cost,car_sell_price.clearance_cost,car_sell_price.transportation_cost, car_make.name AS carMakerName , car_model.name AS carModelName,transport_request.received_create_date,
        auction.title AS aTitle,customer_limit.shipping_commission, auction_location.auction_location_name,port.port_name, customer.full_name AS CustomerName,customer_group.title as customer_group,customer_group.id AS group_id";

        $query = DB::Table('car')
        ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
        ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
        ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
        ->leftJoin('auction', 'auction.id', '=', 'car.auction_id')
        ->leftJoin('auction_location', 'auction_location.auction_location_id', '=', 'car.auction_location_id')
        ->leftJoin('port', 'port.port_id', '=', 'car.destination')
        ->leftJoin('container_car', 'container_car.car_id', '=', 'car.id')
        ->leftJoin('transport_request', 'transport_request.container_id', '=', 'container_car.container_id')
        ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
        ->leftJoin('customer_limit', 'customer_limit.customer_id', '=', 'car.customer_id')
        ->leftJoin('customer_group', 'customer_group.id', '=', 'customer.customer_group_id')
        ->where('car.customer_id', $customer_id)
        ->where('car.deleted', '0')
        ->where('car.cancellation', '0')
        ->where('car.arrive_store', '0')
        ->groupBy('car.id')
        ->orderBy('car.create_date', 'desc');

        $query->select(DB::raw($select));
        return $query->get()->toArray();
    }

    public static function getCarShippingDetail($args){

        if(empty($args['car_id'])){
            return [];
        }
        $car_id = $args['car_id'];

        $select = "car.*,customer.full_name customer_name, customer.full_name_ar customer_name_ar, customer.customer_type, customer.membership_id,
        customer_limit.shipping_commission,car_sell_price.*";

        $query = DB::Table('car')
        ->leftJoin('car_sell_price', 'car_sell_price.car_id', '=', 'car.id')
        ->leftJoin('customer', 'customer.customer_id', '=', 'car.customer_id')
        ->leftJoin('customer_limit', 'customer_limit.customer_id', '=', 'car.customer_id')
        ->where('car.id', $car_id);

        $query->select(DB::raw($select));
        return $query->first();
    }


    public static function getCarsFinalInvoices($args)
    {
        $limit = !empty($args['limit']) ? $args['limit'] : 10;
        $page = !empty($args['page']) ? $args['page'] : 0;

        $select = [
            DB::raw('@a:=@a+1 index_no'),
            'final_payment_invoices_details.car_id as carID', 'final_payment_invoices_details.storage_fine', 'final_payment_invoices_details.remaining_amount',
            'final_payment_invoices_details.amount_paid', 'car.lotnumber', 'car_make.name AS carMakerName', 'car_model.name AS carModelName', 'car.year',
            'final_payment_invoices_details.created_date AS arrival_date', 'car.photo'
        ];

        $query = CarAccounting::getQueryCarsFinalInvoices($args);
        $query->join(DB::raw('(SELECT @a:= ' . $page * $limit . ') as a'), function () {
        })
            ->skip($page * $limit)->take($limit);

        $query->select($select);
        return $query->get()->toArray();
    }

    public static function getCarsFinalInvoicesCount($args)
    {
        $query = CarAccounting::getQueryCarsFinalInvoices($args);
        $query->select(DB::raw('COUNT(final_payment_invoices_details.final_payment_invoices_details_id) as totalRecords'));
        return $query->first()->totalRecords;
    }

    public static function getQueryCarsFinalInvoices($args)
    {
        $query = DB::Table('final_payment_invoices_details')
            ->leftJoin('car', 'car.id', '=', 'final_payment_invoices_details.car_id')
            ->leftJoin('car_make', 'car_make.id_car_make', '=', 'car.id_car_make')
            ->leftJoin('car_model', 'car_model.id_car_model', '=', 'car.id_car_model')
            ->where('car.customer_id', $args['customer_id'])
            ->where('final_payment_invoices_details.final_payment_invoices_id', "!=", 0)
            ->orderBy('final_payment_invoices_details.created_date', 'desc');

        if (!empty($args['lotnumber'])) {
            $query->where('car.lotnumber', $args['lotnumber']);
        }

        if (!empty($args['date_from'])) {
            $query->whereDate("final_payment_invoices_details.created_date", '>=', $args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $query->whereDate("final_payment_invoices_details.created_date", '<=', $args['date_to']);
        }

        return $query;
    }

}
