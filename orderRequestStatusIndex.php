<?php
/**
 * メイン機能と連携するアプリのサンプルです。
 *
 * @copyright Hamee Corp. All Rights Reserved.
 *
*/
require_once(dirname(__FILE__).'/neApiClient.php');
require_once(dirname(__FILE__).'/ReceiveOrder.php');

// この値を「アプリを作る->API->テスト環境設定」の値に更新して下さい。
// (アプリを販売する場合は本番環境設定の値に更新して下さい)
// このサンプルでは、利用者情報取得・マスタ情報取得・受注情報取得にアクセスするため、ネクストエンジン画面から許可して下さい。
define('CLIENT_ID', 'XXXXXXXXXXXXXX');
define('CLIENT_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');

// 本SDKは、ネクストエンジンログインが必要になるとネクストエンジンのログイン画面に
// リダイレクトします。ログイン成功後に、リダイレクトしたい
// アプリケーションサーバーのURIを指定して下さい。
// 呼び出すAPI毎にリダイレクト先を変更したい場合は、apiExecuteの引数に指定して下さい。
$pathinfo = pathinfo(strtok($_SERVER['REQUEST_URI'], '?'));
$redirectUri = 'https://'.$_SERVER['HTTP_HOST'].$pathinfo['dirname'].'/'.$pathinfo['basename'];

$client = new neApiClient(CLIENT_ID, CLIENT_SECRET, $redirectUri);

/*
 *
 * ここから受注伝票の情報を取得する処理
 *
*/

function getStatusArray(ReceiveOrder $receiveOrder, Array $beforeReleaseGoods)
{
    $statusArray = [];
    if ($receiveOrder->isNewStatus()) {
        $statusArray[] = '<span class="label label-warning">新規受付</span>';
    }
    if ($receiveOrder->isConfirmWaitStatus()) {
        $statusArray[] = '<span class="label label-info">確認待ち</span>';
    }
    if ($receiveOrder->isDepositWaitStatus()) {
        $statusArray[] = '<span class="label label-success">入金待ち</span>';
    }
    if ($receiveOrder->isAllocationWaitStatus()) {
        $statusArray[] = '<span class="label label-danger">引当待ち</span>';
    }
    if ($receiveOrder->isReleaseWaitStatus($beforeReleaseGoods)) {
        $statusArray[] = '<span class="label label-primary">発売日待ち</span>';
    }
    if ($receiveOrder->isPrintingDateWaitStatus()) {
        $statusArray[] = '<span class="label label-brown">印刷日待ち</span>';
    }
    if ($receiveOrder->isPrintingWaitStatus()) {
        $statusArray[] = '<span class="label label-pink">印刷待ち</span>';
    }
    if ($receiveOrder->isPrintedStatus()) {
        $statusArray[] = '<span class="label label-purple">印刷済み</span>';
    }
    if ($receiveOrder->isSendStatus()) {
        $statusArray[] = '<span class="label label-violet">出荷済み</span>';
    }
    if ($receiveOrder->isFinishStatus()) {
        $statusArray[] = '<span class="label label-default">処理済み</span>';
    }
    return $statusArray;
}

function requestOrderUrl($host, $orderRequestId)
{
    return "${host}/Userjyuchu/jyuchuInp?kensaku_denpyo_no=${orderRequestId}&jyuchu_meisai_order=jyuchu_meisai_gyo";
}

// 指定されたカラムを配列で返す
// php5.5からarray_columnという関数があるが、今回の動作環境は5.4なのでほぼ同様の機能の関数（array_columnよりは簡素なもの）を自前で実装した
function arrayColumn(array $baseOrderRequest, $column)
{
    $columnArray = [];
    foreach ($baseOrderRequest as $value) {
        $columnArray[] = $value[$column];
    }
    return $columnArray;
}

function executeSearch(neApiClient $client, $path, array $fields, array $opts = [])
{
    $params = $opts;
    $params['fields'] = implode(',', $fields);
    return $client->apiExecute($path, $params);
}

function generateReceiveOrders($baseOrderRequest, $detailOrderRequest)
{
    $receiveOrders = [];
    foreach ($baseOrderRequest as $base) {
        $eachDetail = [];
        foreach ($detailOrderRequest as $detail) {
            if ($base['receive_order_id'] === $detail['receive_order_id']) {
                $eachDetail[] = $detail;
            }
        }
        $receiveOrders[] = new ReceiveOrder($base, $eachDetail);
    }
    return $receiveOrders;
}

/*
 *
 * メイン実行部
 *
 *
*/

// 受注伝票の取得
// 取得するフィールド一覧
$baseFields = [
    'receive_order_id',
    'receive_order_shop_cut_form_id',
    'receive_order_confirm_check_id',
    'receive_order_order_status_id',
    'receive_order_cancel_type_id',
    'receive_order_deposit_type_id',
    'receive_order_deleted_flag',
    'receive_order_payment_method_id',
    'receive_order_payment_method_name',
    'receive_order_statement_delivery_instruct_printing_date',
    'receive_order_send_date'
    ];
// オプション（件数制限）
$baseOptions = ['limit' => 50];
$baseOrderRequest = executeSearch($client, '/api_v1_receiveorder_base/search', $baseFields, $baseOptions);


// 受注伝票の取得の処理に失敗した場合（API通信エラー等）これ以降の処理をしない
if($baseOrderRequest['result'] === 'error'){
    echo("受注伝票取得のAPIでエラーが発生しました。しばらく時間を空けてからお試しください。\n");
    echo("code: " . $baseOrderRequest['code'] . "\n");
    echo("message: " . $baseOrderRequest['message'] . "\n");
    exit;
}

// 受注伝票が1件もない場合には伝票明細は取れないのでこれ以降の処理をしない
if($baseOrderRequest['count'] === '0'){
    echo('受注伝票が1件もありません。伝票を起票してお試しください。');
    exit;
}

// 受注明細の取得
// 取得するフィールド一覧
$detailFields = [
    'receive_order_id',
    'receive_order_row_stock_allocation_quantity',
    'receive_order_row_quantity',
    'receive_order_row_no',
    'receive_order_row_goods_id'
    ];
// オプション（検索条件）
$receiveOrderIds = arrayColumn($baseOrderRequest['data'], 'receive_order_id');
$detailOptions = ['receive_order_id-in' => implode(',', $receiveOrderIds)];
$detailOrderRequest = executeSearch($client, '/api_v1_receiveorder_row/search', $detailFields, $detailOptions);

// 受注伝票明細の取得の処理に失敗した場合（API通信エラー等）これ以降の処理をしない
if($detailOrderRequest['result'] === 'error'){
    echo("受注伝票明細取得のAPIでエラーが発生しました。しばらく時間を空けてからお試しください。\n");
    echo("code: " . $detailOrderRequest['code'] . "\n");
    echo("message: " . $detailOrderRequest['message'] . "\n");
    exit;
}

// 受注伝票明細が1件もない場合にはこれ以降の処理をしない
if($detailOrderRequest['count'] === '0'){
    echo('受注伝票明細が1件もありません。明細のある伝票を起票してお試しください。');
    exit;
}

// 受注伝票インスタンスの配列を生成
$receiveOrders = generateReceiveOrders($baseOrderRequest['data'], $detailOrderRequest['data']);

// 発売日待ち商品を取得する
$goodsFields = [
    'goods_id',
    'goods_release_date'
    ];
$goodsOptions = ['goods_release_date-gt' => date('Y-m-d H:i:s')];
$goodsRequest = executeSearch($client, '/api_v1_master_goods/search', $goodsFields, $goodsOptions);

// 商品マスタの取得の処理に失敗した場合（API通信エラー等）これ以降の処理をしない
if($goodsRequest['result'] === 'error'){
    echo("商品マスタ取得のAPIでエラーが発生しました。しばらく時間を空けてからお試しください。\n");
    echo("code: " . $goodsRequest['code'] . "\n");
    echo("message: " . $goodsRequest['message'] . "\n");
    exit;
}

// 発売日待ち商品が1件もない場合にはこれ以降の処理をしない
if($goodsRequest['count'] === '0'){
    echo('発売日待ち商品が1件もありません。商品を登録してお試しください。');
    exit;
}

// 利用者情報（ホストを特定するのに使用）
$company = $client->apiExecute('/api_v1_login_company/info');
// 利用者情報の取得の処理に失敗した場合（API通信エラー等）これ以降の処理をしない
if($company['result'] === 'error'){
    echo("利用者情報取得のAPIでエラーが発生しました。しばらく時間を空けてからお試しください。\n");
    echo("code: " . $company['code'] . "\n");
    echo("message: " . $company['message'] . "\n");
    exit;
}
$companyHost = $company['data'][0]['company_host'];

?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="sample.css">
        <title>サンプルアプリ</title>
    </head>
    <body>

        <div class="container-fluid">
            <h1>サンプルアプリ</h1>
            <table class='table table-bordered'>
                <thead>
                    <tr>
                        <th>伝票番号</th>
                        <th>受注番号</th>
                        <th>ステータス</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receiveOrders as $receiveOrder) { ?>
                        <tr>
                            <td><?= $receiveOrder->getOrderId(); ?></td>
                            <td><a href=<?= requestOrderUrl($companyHost, $receiveOrder->getOrderId()); ?> target='_blank'>
                                <?= $receiveOrder->getOrderNumber(); ?>
                            </a></td>
                            <td>
                                <?php $statusArray = getStatusArray($receiveOrder, $goodsRequest['data']); ?>
                                <?php foreach ($statusArray as $status) { ?>
                                    <?= $status.' '; ?>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </body>
</html>