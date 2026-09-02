<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ .
    '/services/RepoImageService.php';
    require_once __DIR__ .
    '/services/AdminNotificationService.php';

require_once __DIR__ .
    '/controllers/AdminNotificationController.php';
require_once __DIR__ . '/services/AdminPaymentService.php';
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/services/VehicleService.php';
require_once __DIR__ . '/services/InvoiceService.php';
require_once __DIR__ . '/services/YardService.php';
require_once __DIR__ . '/services/SearchHistoryService.php';
require_once __DIR__ . '/services/ReportService.php';
require_once __DIR__ . '/services/ExcelService.php';
require_once __DIR__ . '/services/ExcelReportService.php';
require_once __DIR__ .
    '/controllers/RepoImageController.php';
require_once __DIR__ . '/controllers/AdminPaymentController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/VehicleController.php';
require_once __DIR__ . '/controllers/InvoiceController.php';
require_once __DIR__ . '/controllers/YardController.php';
require_once __DIR__ . '/controllers/SearchHistoryController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/controllers/ExcelController.php';
require_once __DIR__ .
    '/services/InvoicePaymentService.php';

require_once __DIR__ .
    '/controllers/InvoicePaymentController.php';
require_once __DIR__ .
    '/controllers/UserPaymentController.php';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo=db();
$user=new UserController(
    new UserService($pdo));
$adminNotificationService =
    new AdminNotificationService($pdo);

$adminNotification =
    new AdminNotificationController(
        $adminNotificationService
    );

$vehicle =
new VehicleController(
    new VehicleService(
        $pdo,
        $adminNotificationService
    ),
    new ExcelService($pdo)
);
    $invoice=
new InvoiceController(
    new InvoiceService($pdo));
$invoicePayment =
new InvoicePaymentController(
        new InvoicePaymentService($pdo)
    );
   $adminPayment =
    new AdminPaymentController(
        $pdo,
        new AdminPaymentService($pdo)
    );
    $userPayment =
    new UserPaymentController(
        $pdo,
        new AdminPaymentService($pdo)
    );
$yard=new YardController(new YardService($pdo));
$history=new SearchHistoryController(new SearchHistoryService($pdo));
$report=new ReportController(new ReportService($pdo),new ExcelReportService());
$excel=new ExcelController();
$repoImage =
new RepoImageController(
    new RepoImageService($pdo)
);

$method=$_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove the project folder from the URL
$basePath = '/RepoMasterPHP';

if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

$path = rtrim($path, '/') ?: '/';

try {
 if($method==='POST'&&$path==='/api/admin/register')$user->registerAdmin();
 elseif($method==='POST'&&$path==='/api/users/register')$user->registerUser();
 elseif($method==='POST'&&$path==='/api/login')$user->login();
 elseif($method==='GET'&&$path==='/api/admin/pending-users')$user->pending();
 elseif($method==='PUT'&&preg_match('#^/api/admin/approve/(\d+)$#',$path,$m))$user->approve($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/admin/reject/(\d+)$#',$path,$m))$user->reject($m[1]);
 elseif($method==='GET'&&$path==='/api/users/profile')$user->profile();
 elseif($method==='PUT'&&preg_match('#^/api/users/(\d+)$#',$path,$m))$user->update($m[1]);
 elseif($method==='POST'&&$path==='/api/forgot-password')$user->forgot();
 elseif($method==='PUT'&&$path==='/api/reset-password')$user->reset();
 elseif($method==='GET'&&$path==='/api/verify-email')$user->verify();
 elseif($method==='GET'&&$path==='/api/admin/users')$user->users();
 elseif($method==='GET'&&$path==='/api/admin/search-users')$user->search();
 elseif($method==='DELETE'&&preg_match('#^/api/admin/delete-user/(\d+)$#',$path,$m))$user->delete($m[1]);
 elseif($method==='GET'&&$path==='/api/admin/approved-users')$user->approved();
 elseif($method==='GET'&&$path==='/api/pending/count')$user->pendingCount();
 elseif($method==='GET'&&$path==='/api/admin/download-template')$excel->template();
 elseif($method==='GET'&&$path==='/api/vehicles')$vehicle->list();
 elseif($method==='POST'&&$path==='/api/vehicles')$vehicle->add();
 elseif($method==='POST'&&$path==='/api/vehicles/upload-excel')$vehicle->upload();
 elseif($method==='POST'&&$path==='/api/vehicles/bulk')$vehicle->bulk();
 elseif($method==='GET'&&$path==='/api/vehicles/search')$vehicle->search();
 elseif($method==='GET'&&preg_match('#^/api/vehicles/yard/(\d+)$#',$path,$m))$vehicle->yard($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/vehicles/([^/]+)/status$#',$path,$m))$vehicle->status($m[1]);
 elseif(
    $method === 'POST' &&
    preg_match(
        '#^/api/vehicles/([^/]+)/repo-images$#',
        $path,
        $m
    )
)
    $repoImage->upload(
        $m[1]
    );
    elseif(
    $method === 'GET' &&
    $path === '/api/admin/repo-images'
)
    $repoImage->listUploadedImages();

elseif(
    $method === 'GET' &&
    preg_match(
        '#^/api/admin/repo-images/(\d+)$#',
        $path,
        $m
    )
)
    $repoImage->getUploadedImage(
        (int)$m[1]
    );
    elseif(
    $method === 'DELETE' &&
    preg_match(
        '#^/api/admin/repo-images/(\d+)$#',
        $path,
        $m
    )
)
    $repoImage->deleteUploadedImages(
        (int)$m[1]
    );
 elseif($method==='PUT'&&preg_match('#^/api/vehicles/([^/]+)/assign-yard$#',$path,$m))$vehicle->assign($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/vehicles/([^/]+)/remove-yard$#',$path,$m))$vehicle->removeYard($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/vehicles/([^/]+)$#',$path,$m))$vehicle->update($m[1]);
 elseif($method==='DELETE'&&preg_match('#^/api/vehicles/([^/]+)$#',$path,$m))$vehicle->delete($m[1]);
 elseif($method==='GET'&&preg_match('#^/api/vehicles/([^/]+)$#',$path,$m))$vehicle->get($m[1]);
 elseif($method==='POST'&&$path==='/api/invoices')$invoice->add();
 elseif($method==='GET'&&$path==='/api/invoices')$invoice->list();
 elseif($method==='GET'&&preg_match('#^/api/invoices/(\d+)$#',$path,$m))$invoice->get($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/invoices/(\d+)/payment$#',$path,$m))$invoice->updatePayment($m[1]);
 elseif($method==='DELETE'&&preg_match('#^/api/invoices/(\d+)$#',$path,$m))$invoice->delete($m[1]);
 elseif(
    $method === 'POST' &&
    preg_match(
        '#^/api/invoices/(\d+)/payments$#',
        $path,
        $m
    )
)
    $invoicePayment->add(
        (int)$m[1]
    );

elseif(
    $method === 'GET' &&
    preg_match(
        '#^/api/invoices/(\d+)/payments$#',
        $path,
        $m
    )
)
    $invoicePayment->list(
        (int)$m[1]
    );

elseif(
    $method === 'GET' &&
    preg_match(
        '#^/api/invoice-payments/(\d+)$#',
        $path,
        $m
    )
)
    $invoicePayment->get(
        (int)$m[1]
    );

elseif(
    $method === 'DELETE' &&
    preg_match(
        '#^/api/invoice-payments/(\d+)$#',
        $path,
        $m
    )
)
    $invoicePayment->delete(
        (int)$m[1]
    );
 elseif($method==='GET'&&$path==='/api/yards')$yard->list();
 elseif($method==='POST'&&$path==='/api/yards')$yard->add();
 elseif($method==='GET'&&preg_match('#^/api/yards/(\d+)$#',$path,$m))$yard->get($m[1]);
 elseif($method==='PUT'&&preg_match('#^/api/yards/(\d+)$#',$path,$m))$yard->update($m[1]);
 elseif($method==='DELETE'&&preg_match('#^/api/yards/(\d+)$#',$path,$m))$yard->delete($m[1]);
 elseif($method==='POST'&&$path==='/api/search-history/save')$history->save();
 elseif($method==='GET'&&$path==='/api/search-history')$history->list();
 elseif($method==='GET'&&$path==='/api/search-history/admin/all')$history->all();
 elseif($method==='GET'&&$path==='/api/search-history/search')$history->search();
 elseif($method==='GET'&&$path==='/api/search-history/filter/user')$history->user();
 elseif($method==='GET'&&$path==='/api/search-history/filter/date')$history->date();
 elseif($method==='GET'&&$path==='/api/search-history/sort')$history->sort();
 elseif($method==='GET'&&$path==='/api/reports/summary')$report->summary();
 elseif($method==='GET'&&$path==='/api/reports/finance')$report->finance();
 elseif($method==='GET'&&$path==='/api/reports/monthly')$report->monthly();
 elseif($method==='GET'&&$path==='/api/reports/user-activity')$report->activity();
 elseif($method==='GET'&&$path==='/api/reports/finance-list')$report->financeList();
 elseif($method==='GET'&&$path==='/api/reports/branch-list')$report->branchList();
 elseif($method==='GET'&&$path==='/api/reports/vehicles')$report->vehicles();
 elseif($method==='GET'&&$path==='/api/reports/user')$report->userReport();
 elseif($method==='GET'&&$path==='/api/reports/user/excel')$report->userExcel();
 elseif($method==='GET'&&preg_match('#^/api/reports/finance/excel/([^/]+)$#',$path,$m))$report->financeExcel($m[1]);
 elseif($method==='GET'&&preg_match('#^/api/reports/user-activity/excel/([^/]+)$#',$path,$m))$report->activityExcel($m[1]);
 elseif($method==='GET'&&preg_match('#^/api/reports/monthly/excel/([^/]+)$#',$path,$m))$report->monthlyExcel($m[1]);
 

// ===============================
// ADMIN NOTIFICATIONS
// ===============================

elseif(
    $method === 'GET' &&
    $path === '/api/admin/notifications'
)
    $adminNotification->list();

elseif(
    $method === 'GET' &&
    $path === '/api/admin/notifications/unread-count'
)
    $adminNotification->unreadCount();

elseif(
    $method === 'PUT' &&
    preg_match(
        '#^/api/admin/notifications/(\d+)/read$#',
        $path,
        $m
    )
)
    $adminNotification->markRead(
        (int)$m[1]
    );

elseif(
    $method === 'GET' &&
    preg_match(
        '#^/api/reports/yard/excel/(\d+)$#',
        $path,
        $m
    )
)
    $report->yardExcel($m[1]);

 elseif($method==='GET'&&preg_match('#^/api/reports/yard/excel/(\d+)$#',$path,$m))$report->yardExcel($m[1]);
elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/users'
)
    $adminPayment->users();

elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/user-vehicles'
)
    $adminPayment->userVehicles();
    elseif(
    $method === 'POST' &&
    $path === '/api/admin/payment'
)
    $adminPayment->createPayment();
    elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/rates'
)
    $adminPayment->rates();

elseif(
    $method === 'POST' &&
    $path === '/api/admin/payment/rates'
)
    $adminPayment->saveRate();

elseif(
    $method === 'DELETE' &&
    preg_match(
        '#^/api/admin/payment/rates/(\d+)$#',
        $path,
        $m
    )
)
    $adminPayment->deleteRate(
        (int)$m[1]
    );
    elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/calculate'
)
    $adminPayment->calculate();
    elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/summary'
)
    $adminPayment->summary();
    elseif(
    $method === 'GET' &&
    $path === '/api/admin/payment/history'
)
    $adminPayment->history();
    elseif (
    $method === 'GET' &&
    $path === '/api/user/payment/history'
)
    $userPayment->history();

elseif (
    $method === 'GET' &&
    $path === '/api/user/payment/summary'
)
    $userPayment->summary();
    elseif (
    $method === 'PUT' &&
    preg_match(
        '#^/api/users/(\d+)/status$#',
        $path,
        $matches
    )
) {
    $user->updateStatus(
        (int)$matches[1]
    );
}
 else errorResponse('API endpoint not found',404);
} catch(Throwable $e) { errorResponse($e->getMessage(),500); }
