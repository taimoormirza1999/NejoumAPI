<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/


$router->get('/', function () use ($router) {
    return "";
});

$router->get('/api', function() use ($router) {
    return "Saqib...";
});


//, 'middleware' => ['auth:web','checkAdmin']
$router->group(['prefix' => 'api'], function () use ($router) {

    //Cars APIs
    $router->get('showAllCars',  ['uses' => 'Car\CarController@showAllCars']);
    $router->get('newCars',  ['middleware' => 'client','uses' => 'Car\CarController@newCars']);
    $router->get('towingCars',  ['middleware' => 'client','uses' => 'Car\CarController@towingCars']);
    $router->get('warehouseCars',  ['middleware' => 'client','uses' => 'Car\CarController@warehouseCars']);
    $router->get('portCars',  ['middleware' => 'client','uses' => 'Car\CarController@portCars']);
    $router->get('onWayCars',  ['middleware' => 'client','uses' => 'Car\CarController@onWayCars']);
    $router->get('arrivedCars',  ['middleware' => 'client','uses' => 'Car\CarController@arrivedCars']);
    $router->get('deliveredCars',  ['middleware' => 'client','uses' => 'Car\CarController@deliveredCars']);
    $router->get('customer/allCancelledCars/',  ['middleware' => 'client','uses' => 'Car\CarController@getCustomerAllCancelledCars']);
    $router->get('carsByWarehouseRegion',  ['middleware' => 'client','uses' => 'Car\CarController@warehouseRegion']);
    $router->get('statesCount',  ['middleware' => 'client','uses' => 'Car\CarController@statesCount']);
    $router->get('carsArrivedStore',  ['middleware' => 'client','uses' => 'Car\CarController@carsArrivedStore']);
    $router->get('dashboard/cars/count',  ['middleware' => 'client','uses' => 'Car\CarController@dashboardCarsCount']);
    $router->get('getCustomerBalance',  ['middleware' => 'client','uses' => 'Accounting\StatementController@getCustomerBalanceTempAuth']);
    $router->get('customer/containersCount',  ['middleware' => 'auth', 'uses' => 'Car\CarController@containersCount']);
    $router->get('customer/containers',  ['middleware' => 'client','uses' => 'Car\CarController@customerContainers']);
    $router->get('customer/container/export',  ['middleware' => 'client','uses' => 'Car\CarController@containerExport']);
    $router->get('customer/container/detail',  ['middleware' => 'client','uses' => 'Car\CarController@containerDetail']);
    $router->get('customer/container/invoice',  ['middleware' => 'client','uses' => 'Car\CarController@containerInvoice']);
    //Images APIs
    $router->get('getImages',  ['uses' => 'Car\ImgController@getImages']);
    $router->get('getDownloadableImages',  ['uses' => 'Car\ImgController@getDownloadableImages']);
    //$router->post('carImages',  ['uses' => 'Car\ImgController@getAllImages']);
    //$router->post('warehouseImages',  ['uses' => 'Car\ImgController@getwarehouseImages']);

    //Authentication APIs
    $router->post('login', ['middleware' => 'apiLogin', 'uses' => 'Auth\AuthController@login']);
    $router->post('logout', ['middleware' => ['apiLogin', 'auth'], 'uses' => 'Auth\AuthController@logout']);
    $router->post('refresh', ['middleware' => 'apiLogin', 'uses' => 'Auth\AuthController@refresh']);
    $router->post('checkCustomerLoggedin', ['uses' => 'UserController@checkCustomerLoggedin']);
    $router->post('changePassword', ['middleware' => 'client','uses' => 'UserController@changePassword']);

    $router->post('employee/login', ['middleware' => 'apiLogin', 'uses' => 'Auth\EmployeeController@login']);
    $router->post('employee/logout', ['middleware' => ['apiLogin', 'auth'], 'uses' => 'Auth\EmployeeController@logout']);

    //Dashboard APIs
    $router->post('dashboardCount',  ['middleware' => 'auth', 'uses' => 'Dashboard\DashboardController@DashboardCount']);
    $router->get('adsAnnouncement',  ['uses' => 'Dashboard\DashboardController@siteAdvertisment']);
    $router->get('profile',  ['middleware' => 'client', 'uses' => 'Dashboard\DashboardController@getProfileData']);

    $router->get('warehouseCarRequestExist',  ['middleware' => 'client', 'uses' => 'Car\CarController@warehouseCarRequestExist']);
    $router->get('warehouseCarRequest',  ['middleware' => 'client', 'uses' => 'Car\CarController@warehouseCarRequest']);
    $router->get('warehouseCarRequests',  ['middleware' => 'client', 'uses' => 'Car\CarController@warehouseCarRequests']);
    $router->post('warehouseCarRequest',  ['middleware' => 'client', 'uses' => 'Car\CarController@saveWarehouseCarRequest']);
    $router->post('warehouseCarRequest/customer/approve',  ['middleware' => 'client', 'uses' => 'Car\CarController@customerApproveWarehouseCarRequest']);
    $router->delete('warehouseCarRequest',  ['middleware' => 'client', 'uses' => 'Car\CarController@deleteWarehouseCarRequest']);
    $router->post('customer/agencyDocument',  ['middleware' => 'client', 'uses' => 'CustomerService\CustomerController@saveAgencyDocument']);
    $router->get('customer/agencyDocument',  ['middleware' => 'client', 'uses' => 'CustomerService\CustomerController@getAgencyDocument']);
    $router->get('customer/hasAgencyDocument',  ['middleware' => 'client', 'uses' => 'CustomerService\CustomerController@hasAgencyDocument']);

    // Car Statement

    $router->get('carStatement/shippedCars',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementShippedCars']);
    $router->get('carStatement/shippedCars/pdf',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementShippedCarsPDF']);

    // Statement related apis not used START:
    $router->get('carStatement/carsInAuction',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementInAuctionCars']);
    $router->get('carStatement/carsInAuction/pdf',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementInAuctionCarsPDF']);
    $router->get('carStatement/generalEntries',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementGeneralEntries']);
    $router->get('carStatement/generalEntries/pdf',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementGeneralEntriesPDF']);
    // Statement related apis not used END:

    $router->get('carStatement/deposits',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementDeposits']);
    $router->get('carStatement/deposit/detail',  ['middleware' => 'client', 'uses' => 'Accounting\StatementController@carStatementDepositDetail']);
    $router->get('getPricesLists', ['middleware' => 'client','uses' => 'Car\CarController@getPricesLists']);


    //No Auth
    $router->get('carStatement/shippedCarsnoAuth',  [ 'uses' => 'Accounting\StatementController@carStatementShippedCarsNoAuth']);
    $router->get('carStatement/InAuctionCarsnoAuth',  [ 'uses' => 'Accounting\StatementController@carStatementInAuctionCarsNoAuth']);
    $router->get('carStatement/GeneralEntriesnoAuth',  [ 'uses' => 'Accounting\StatementController@carStatementGeneralEntriesNoAuth']);
    //$router->get('getCustomerBalancenoAuth',  ['uses' => 'Car\CarController@getCustomerBalancenoAuth']);
    $router->get('getCustomerBalancenoAuth',  ['uses' => 'Accounting\StatementController@getCustomerBalanceTemp']);


    //General
    $router->get('getTrackSearch',  ['middleware' => 'client','uses' => 'Car\CarController@getTrackSearch']);
    $router->get('getTrackSearchExternal',  ['uses' => 'Car\CarController@getTrackSearch']);

    $router->get('getVehicleType',  ['uses' => 'General\GeneralController@getVehicleType']);
    $router->get('getAuction',  ['uses' => 'General\GeneralController@getAuction']);
    $router->get('getAuctionLocation',  ['uses' => 'General\GeneralController@getAuctionLocation']);
    $router->get('getCountries',  ['uses' => 'General\GeneralController@getCountries']);
    $router->post('send-bill-whatsapp',  [ 'uses' => 'External\WhatsAppController@sendBillToCustomer']);// add Auth later
    $router->post('notify-towing-case-cs',  [ 'uses' => 'External\WhatsAppController@notifyTowingCaseCS']);// add Auth later
    $router->post('whatsapp-test-ib',  [ 'uses' => 'External\WhatsAppController@whatsapp_test']);// add Auth later

     //Maersk APIs - For Sync
     $router->get('shipment-deadlines',  ['uses' => 'Maersk\DeadLinesController@index']);
     $router->get('demurrages',  ['uses' => 'Maersk\DemurrageAndDetentionController@index']);
     $router->get('track-and-trace',  ['uses' => 'Maersk\trackAndTraceController@index']);

     // Maersk APIS - For dashboard
     $router->get('invoices/{type}',  ['uses' => 'Maersk\InvoiceController@index']);
     $router->get('track-booking/{booking_number}',  ['uses' => 'Maersk\trackAndTraceController@getEventsForBooking']);


     // internal dashboard statistics
     $router->get('statistics/demurrage',  ['uses' => 'System\DashboardController@getBookingsUnderDemurrage']);
     $router->get('statistics/bookings-loaded',  ['uses' => 'System\DashboardController@getLoadedBookings']);
     $router->get('statistics/bookings-discharged',  ['uses' => 'System\DashboardController@getDischargedBookings']);
     $router->get('statistics/bookings-arrival',  ['uses' => 'System\DashboardController@getBookingsArrival']);
     $router->get('bookings-arriving-soon',  ['uses' => 'System\DashboardController@getBookingsArrivingSoon']);



    //Complaints
    $router->post('submitComplaint',  ['middleware' => 'client', 'uses' => 'General\GeneralController@sendMsg']);
    $router->post('submitComplaintNoAuth',  ['uses' => 'General\GeneralController@sendMsg']);

    $router->post('submitComplaintChat',  ['middleware' => 'client', 'uses' => 'General\GeneralController@sendChatMessage']);
    $router->post('submitComplaintChatNoAuth',  ['uses' => 'General\GeneralController@sendChatMessage']);
    $router->post('submitComplaintChatNoAuth2',  ['uses' => 'General\GeneralController@sendChatMessageMultiImages']);
    $router->get('complaintTypes',  ['uses' => 'General\GeneralController@getComplaintTypes']);
    $router->post('complaintMessageId',  ['uses' => 'General\GeneralController@getcomplaintMessageId']);
    $router->post('submitComplaintChat',  ['middleware' => 'client', 'uses' => 'General\GeneralController@sendChatMessage']);
    $router->get('complaintMessage',  ['middleware' => 'client', 'uses' => 'General\GeneralController@complaintMessage']);
    $router->get('complaintMessageNoAuth',  ['uses' => 'General\GeneralController@complaintMessageNoAuth']);
    $router->get('complaintMessageChatsNoAuth',  ['uses' => 'General\GeneralController@getMsgChat']);
    $router->get('complaintMessageChatsNoAuth2',  ['uses' => 'General\GeneralController@getMsgChatMultiImages']);
    $router->get('complaintMessageDetailsNoAuth',  [ 'uses' => 'General\GeneralController@complaintMessageDetails']);
    $router->get('complaintMessageDetails',  ['middleware' => 'client', 'uses' => 'General\GeneralController@complaintMessageDetails']);
    $router->get('car/shippingBillDetailnoAuth/{car_id}',  ['uses' => 'Accounting\CarAccountingController@shippingBillDetail']);
    $router->get('car/shippingBillDetail/{car_id}',  ['middleware' => 'client', 'uses' => 'Accounting\CarAccountingController@shippingBillDetail']);
    $router->get('car/shippingBillDetailPrint/{car_id}',  ['middleware' => 'client', 'uses' => 'Accounting\CarAccountingController@shippingBillDetailPrint']);

    $router->post('car/saveDeliveredToCustomer',  ['middleware' => 'client', 'uses' => 'Car\CarController@saveDeliveredToCustomer']);
    $router->post('car/saveArrivedToStore',  ['middleware' => 'client', 'uses' => 'Car\CarController@saveArrivedToStore']);
    $router->post('car/saveArrivedToPort',  ['middleware' => 'client', 'uses' => 'Car\CarController@saveArrivedToPort']);


    $router->get('cashier/arrivedCars/',  ['uses' => 'Accounting\CashierController@arrivedCarsShippingCost']);
    $router->get('cashier/arrivedCarsCount/',  ['uses' => 'Accounting\CashierController@arrivedCarsShippingCostCount']);
    // Storage fine
    $router->get('car/storageFine/{car_id}',  ['uses' => 'Accounting\CarAccountingController@storageFine']);
    $router->get('car/storageFine/perDay/{car_id}',  ['uses' => 'Accounting\CarAccountingController@storageFinePerDay']);
    $router->get('car/getExtraDetail/{car_id}',  ['uses' => 'Accounting\CarAccountingController@getExtraDetail']);

    // Arrived car final invoices
    $router->get('cashier/getCarsFinalInvoices',  ['uses' => 'Accounting\CashierController@getCarsFinalInvoices']);
    $router->get('cashier/getCarsFinalInvoicesCount',  ['uses' => 'Accounting\CashierController@getCarsFinalInvoicesCount']);

    /**$router->get('car/getExtraDetail/{car_id}',  ['uses' => 'Accounting\CarAccountingController@getExtraDetail']);

    // Paid car invioces
    $router->get('cashier/getPaidCarInvoices',  ['uses' => 'Accounting\CashierController@getPaidCarInvoices']);
    $router->get('cashier/getPaidCarInvoice/pdf/en/{bill_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoicePrintEN']);
    $router->get('cashier/getPaidCarInvoice/pdf/ar/{bill_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoicePrintAR']);

    $router->get('cashier/getPaidCarInvoiceDetail/{bill_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoiceDetail']);
    $router->get('cashier/getPaidCarInvoiceDetail/pdf/en/{bill_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoiceDetailPrintEN']);
    $router->get('cashier/getPaidCarInvoiceDetail/pdf/ar/{bill_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoiceDetailPrintAR']);

    // Cancelled car invoices
    $router->get('cashier/getCancelledCarInvoices',  ['uses' => 'Accounting\CashierController@getCancelledCarInvoices']);
    $router->get('cashier/getCancelledCarInvoice/pdf/en/{bill_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoicePrintEN']);
    $router->get('cashier/getCancelledCarInvoice/pdf/ar/{bill_id}',  ['uses' => 'Accounting\CashierController@getPaidCarInvoicePrintAR']);

    $router->get('cashier/getCancelledCarInvoiceDetail/{bill_id}',  ['uses' => 'Accounting\CashierController@getCancelledCarInvoiceDetail']);
    $router->get('cashier/getCancelledCarInvoiceDetail/pdf/en/{bill_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getCancelledCarInvoiceDetailPrintEN']);
    $router->get('cashier/getCancelledCarInvoiceDetail/pdf/ar/{bill_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getCancelledCarInvoiceDetailPrintAR']);

    // Paid by customer invoices
    $router->get('cashier/getPaidByCustomerInvoices',  ['uses' => 'Accounting\CashierController@getPaidByCustomerInvoices']);

    // Arrived car final invoices
    $router->get('cashier/getFinalInvoices',  ['uses' => 'Accounting\CashierController@getFinalInvoices']);
    $router->get('cashier/getFinalInvoice/pdf/en/{invoice_id}',  ['uses' => 'Accounting\CashierController@getFinalInvoicePrintEN']);
    $router->get('cashier/getFinalInvoice/pdf/ar/{invoice_id}',  ['uses' => 'Accounting\CashierController@getFinalInvoicePrintAR']);

    $router->get('cashier/getFinalInvoiceDetail/{invoice_id}',  ['uses' => 'Accounting\CashierController@getFinalInvoiceDetail']);
    $router->get('cashier/getFinalInvoiceDetail/pdf/en/{invoice_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getFinalInvoiceDetailPrintEN']);
    $router->get('cashier/getFinalInvoiceDetail/pdf/ar/{invoice_id}/{car_id}',  ['uses' => 'Accounting\CashierController@getFinalInvoiceDetailPrintAR']);


    // Storage fine
    $router->get('car/storageFine/{car_id}',  ['uses' => 'Accounting\CarAccountingController@storageFine']);
    $router->get('car/storageFine/perDay/{car_id}',  ['uses' => 'Accounting\CarAccountingController@storageFinePerDay']);
**/

// Unpaid cars detection
    $router->get('cashier/unpaidCars/',  ['uses' => 'Accounting\CashierController@unpaidCarsInAuction']);
    $router->get('cashier/cancelledCars/',  ['uses' => 'Accounting\CashierController@cancelledCarsInAuction']);
    $router->get('cashier/balanceOfTransferred/',  ['uses' => 'Accounting\CashierController@balanceOfTransferred']);
    $router->get('shippingCalculator/',  ['middleware' => 'client', 'uses' => 'Accounting\AccountingController@shippingCalculator']);
    $router->get('shippingCalculatornoAuth/',  ['uses' => 'Accounting\AccountingController@shippingCalculatornoAuth']);
    $router->get('general/getAuctionLocations/',  ['uses' => 'General\GeneralController@getAuctionLocations']);
    $router->get('general/getCountryPorts/',  ['uses' => 'General\GeneralController@getCountryPorts']);
    $router->get('general/getAuctionsRegions',  ['uses' => 'General\GeneralController@getAuctionsRegions']);
    $router->get('general/getStates',  ['uses' => 'General\GeneralController@getStates']);
    $router->get('general/getCities',  ['uses' => 'General\GeneralController@getCities']);
    $router->get('general/getBankAccount',  ['uses' => 'General\GeneralController@getBankAccount']);
    $router->get('general/getTypeofReceiver',  ['uses' => 'General\GeneralController@getTypeofReceiver']);
    $router->get('general/getAboutUs/',  ['uses' => 'General\GeneralController@getAboutUs']);
    $router->get('general/getAboutUs/ar',  ['uses' => 'General\GeneralController@getAboutUsAR']);
    $router->get('towingCases', ['uses' => 'General\GeneralController@getTowingCases']);
    //Cars For Sale
    $router->get('CarsForSale',  ['uses' => 'CarSell\CarsForSaleController@getAllCarsForSale']);
    $router->get('CarsForSaleDetails',  ['uses' => 'CarSell\CarsForSaleController@getCarDetails']);
    $router->get('getFavoritdata',  ['middleware' => 'client','uses' => 'CarSell\CarsForSaleController@getFavoritdata']);
    $router->get('addtoFavorit',  ['middleware' => 'client','uses' => 'CarSell\CarsForSaleController@addtoFavorit']);
    $router->get('changestatusfave',  ['middleware' => 'client','uses' => 'CarSell\CarsForSaleController@changestatusfave']);
    $router->get('getModelAll',  ['uses' => 'General\GeneralController@getModelAll']);
    $router->get('getModel',  ['uses' => 'General\GeneralController@getModel']);
    $router->get('getMakerAll',  ['uses' => 'General\GeneralController@getMakerAll']);
    $router->get('getMaker',  ['uses' => 'General\GeneralController@getMaker']);
    $router->get('getYear',  ['uses' => 'General\GeneralController@getYear']);
    $router->get('getColors',  ['uses' => 'General\GeneralController@getColors']);
    $router->get('getVehicleTypes',  ['uses' => 'General\GeneralController@getVehicleTypes']);

    $router->get('getPricesLists', ['middleware' => 'client','uses' => 'Car\CarController@getPricesLists']);

    $router->post('contactUs', ['uses' => 'General\GeneralController@contact_us']);
    $router->post('feedback/sendLink',  [ 'uses' => 'External\WhatsAppController@sendFeedbackLink']);
    $router->post('feedback/monthly/sendLink',  [ 'uses' => 'External\WhatsAppController@sendMonthlyFeedbackLink']);
    $router->post('feedback', ['uses' => 'General\GeneralController@saveFeedback']);
    $router->get('feedback/validate', ['uses' => 'General\GeneralController@validateFeedback']);

    $router->get('specialPortCustomerCars', ['uses' => 'Car\CarController@getSpecialPortCustomerCars']);

    $router->get('destinationChangeCars', ['uses' => 'Car\CarController@destinationChangeCars']);
    $router->post('updateCustomerDestination', ['uses' => 'Car\CarController@updateCustomerDestination']);
    $router->post('deleteDestinationRequest', ['uses' => 'Car\CarController@deleteDestinationRequest']);
    $router->post('sendDestinationRequest', ['uses' => 'Car\CarController@sendDestinationRequest']);
    $router->post('specialPortReceivableInfo',  ['uses' => 'Car\CarController@specialPortReceivableInfo']);
    $router->get('getAllDestinationRequest',  ['uses' => 'Car\CarController@getAllDestinationRequest']);
    $router->post('contactUsMarketing', ['uses' => 'General\GeneralController@contact_us_marketing']);

    $router->post('changeReceiverName', ['uses' => 'Car\CarController@changeReceiverName']);


    // Online Payment
    $router->get('getAllOnlinePayment',  ['uses' => 'Car\CarController@getAllOnlinePayment']);
    $router->post('uploadTransfer',  [ 'uses' => 'Car\CarController@uploadTransfer']);
    $router->post('uploadTransferTNew',  [ 'uses' => 'Car\CarController@uploadTransferTNew']);
    $router->post('getTransferFee',  [ 'uses' => 'Car\CarController@getTransferFee']);
    $router->post('deletePaymentRequest', ['uses' => 'Car\CarController@deletePaymentRequest']);
    $router->get('getPaymentDetails', ['uses' => 'Car\CarController@getPaymentDetails']);
    $router->get('getServicesDetails', ['uses' => 'Car\CarController@getServicesDetails']);
    $router->post('uploadTransferOther',  [ 'uses' => 'Car\CarController@uploadTransferOther']);
    $router->get('getAllOnlinePaymentOther',  [ 'uses' => 'Car\CarController@getAllOnlinePaymentOther']);
    $router->get('getPaymentOtherDetails',  [ 'uses' => 'Car\CarController@getPaymentOtherDetails']);

    // Authorized Receiver
    $router->get('getAllAuthorizedReceiver',  ['uses' => 'General\GeneralController@getAllAuthorizedReceiver']);
    $router->post('uploadIDReceiver',  [ 'uses' => 'General\GeneralController@uploadIDReceiver']);
    $router->post('deleteAuthorizedReceiver', ['uses' => 'General\GeneralController@deleteAuthorizedReceiver']);
    $router->post('activateAuthorizedReceiver', ['uses' => 'General\GeneralController@activateAuthorizedReceiver']);
    $router->post('deactivateAuthorizedReceiver', ['uses' => 'General\GeneralController@deactivateAuthorizedReceiver']);
    $router->get('getAuthorizedReceiverDetails', ['uses' => 'General\GeneralController@getAuthorizedReceiverDetails']);
    $router->get('getnonDelivered', ['uses' => 'General\GeneralController@getnonDelivered']);
    $router->get('getnonVcc', ['uses' => 'General\GeneralController@getnonVcc']);

    // general notification
    $router->get('getGeneralNotification',  ['middleware' => 'client', 'uses' => 'Dashboard\DashboardController@getGeneralNotification']);
    $router->post('seenGeneralNotification',  ['middleware' => 'client', 'uses' => 'General\GeneralController@seenGeneralNotification']);
    $router->post('setCustomerToken',  ['middleware' => 'client', 'uses' => 'General\GeneralController@setCustomerToken']);
    $router->get('general/shippingBroker/Naj',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@getNAJShippingBroker']);
    $router->get('general/shippingBrokerCommission/Naj',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@getNAJShippingBrokerCommission']);
    $router->get('customer/groups',  ['middleware' => 'auth', 'uses' => 'CustomerService\CustomerController@customerGroups']);
    $router->get('customer/lists',  ['middleware' => 'auth', 'uses' => 'CustomerService\CustomerController@customerLists']);
    $router->get('customer/activeContract/lists',  ['middleware' => 'auth', 'uses' => 'CustomerService\CustomerController@activeContractCustomerLists']);
    $router->get('customer/lists/current/{customer_id}',  ['middleware' => 'auth', 'uses' => 'CustomerService\CustomerController@customerCurrentLists']);
    $router->get('shipping/averageContainerPrice',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@averageContainerShippingPrice']);
    $router->get('loading/averageContainerPrice',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@averageContainerLoadingPrice']);
    $router->get('system/priceLists',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@getGeneratedPriceLists']);
    $router->get('system/priceLists/edit',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@editGeneratedPriceList']);
    $router->get('system/priceLists/notes',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@getPriceListNotes']);
    $router->post('system/priceLists/noteSave',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@savePriceListNote']);
    $router->post('system/priceLists/noteDelete/{note_id}',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@deletePriceListNote']);
    $router->get('system/listPrices',  ['middleware' => 'auth', 'uses' => 'Accounting\AccountingController@getListPrices']);
    $router->get('system/listPrices/regionAuctionLocations',  ['middleware' => 'auth', 'uses' => 'General\GeneralController@getRegionAuctionLocations']);
    $router->get('getDownloadableImagesTest',  ['uses' => 'Car\ImgController@getDownloadableImagesTest']);
    $router->get('generatePDFTest',  ['uses' => 'External\WhatsAppController@generatePDFTest']);
    $router->get('getBuyerAcc',  ['uses' => 'General\GeneralController@getBuyerAcc']);
    $router->get('getSpecialRequest',  ['uses' => 'General\GeneralController@getSpecialRequest']);
    $router->get('getAllSpecialRequest',  ['uses' => 'General\GeneralController@getAllSpecialRequest']);
    $router->post('addLoadingRequest',  ['uses' => 'General\GeneralController@addLoadingRequest']);
    $router->post('deleteLoadingRequest', ['uses' => 'General\GeneralController@deleteLoadingRequest']);
    $router->get('getExchangeCompanies',  [ 'uses' => 'General\GeneralController@getAllExchangeCompanies']);
    $router->get('convertHEICToJpgUsingImagick',  [ 'uses' => 'General\GeneralController@convertHEICToJpgUsingImagick']);

    //Buyer Common cars
    $router->get('getAllCommonBuyerCars', ['uses' => 'Car\CarController@getAllCommonBuyerCars']);
    $router->post('uploadCommonBuyerCars', ['uses' => 'Car\CarController@uploadCommonBuyerCars']);
    $router->post('deleteCommonBuyerCars', ['uses' => 'Car\CarController@deleteCommonBuyerCars']);

    //Damage cars
    $router->get('getLotNumbersDamage', ['uses' => 'General\GeneralController@getLotNumbersDamage']);
    $router->get('getLotNumbersDamageInfo', ['uses' => 'General\GeneralController@getLotNumbersDamageInfo']);
    $router->post('uploadDamageRequest', ['uses' => 'General\GeneralController@uploadDamageRequest']);
    //$router->post('deleteDamageRequest', ['uses' => 'General\GeneralController@deleteDamageRequest']);
    $router->get('getAllDamageRequest', ['uses' => 'General\GeneralController@getAllDamageRequest']);
    $router->get('getCarInfo', ['uses' => 'General\GeneralController@getCarInfo']);
    $router->get('getDamageParts', ['uses' => 'General\GeneralController@getDamageParts']);

     // Service Request
     $router->post('getCustomerServiceRequest', ['uses' => 'General\GeneralController@getCustomerServiceRequest']);
     $router->post('uploadServiceRequest', ['uses' => 'General\GeneralController@uploadServiceRequest']);
     // Services
     $router->get('getAllAppTraficServices', ['uses' => 'General\GeneralController@getAllAppTraficServices']);

     //Regions
     $router->get('getAllAppTraficRegions', ['uses' => 'General\GeneralController@getAllAppTraficRegions']);
     // Feedback
     $router->post('saveAppFeedback', ['uses' => 'General\GeneralController@saveAppFeedback']);

    $router->post('sendPaidByCustomersCar', ['uses' => 'Car\CarController@sendPaidByCustomersCar']);
    $router->get('getAllPaidByCustomersCar',  ['uses' => 'Car\CarController@getAllPaidByCustomersCar']);
    $router->post('deletePaidByCustomersCar', ['uses' => 'Car\CarController@deletePaidByCustomersCar']);
    $router->get('complaintEmailTest', ['uses' => 'Car\GeneralController@complaintEmailTest']);

});
