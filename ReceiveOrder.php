<?php
/**
 *
 * @copyright Hamee Corp. All Rights Reserved.
 *
*/

class ReceiveOrder
{
    // 受注状態を表す定数
    const ORDER_STATUS_LACK_OF_INFORMATION  = '0';  // 取込情報不足
    const ORDER_STATUS_IMPORT_ORDER_MAIL    = '1';  // 受注メール取込済
    const ORDER_STATUS_ISSUING_PAYMENT_SLIP = '2';  // 起票済
    const ORDER_STATUS_PRINT_WAIT           = '20'; // 納品書印刷待ち
    const ORDER_STATUS_PRINTED              = '40'; // 納品書印刷済
    const ORDER_STATUS_DISPATCH             = '50'; // 出荷確定済

    // 各支払方法を表す定数
    const PAYMENT_CASH_ON_DELIVERY     = '1';  // 代金引換
    const PAYMENT_LATER_PAYMENT        = '16'; // 銀行振込後払い
    const PAYMENT_SAMPLE_OR_LENDING    = '90'; // サンプル・貸し出し
    const PAYMENT_SELLING_ON_CREDIT    = '95'; // 掛売

    // 各フラグ
    const AVAILABLE_ORDER      = '0';  // 有効な受注
    const DELETED              = '1';  // 削除されている
    const NEED_CONFIRM         = '1';  // 要確認
    const DEPOSITED            = '2';  // 入金済

    private $base;
    private $detail;

    public function __construct(array $base, array $details)
    {
        $this->base    = $base;
        $this->details = $details;
    }

    // アクセスメソッド
    public function getOrderId()
    {
        return $this->base['receive_order_id'];
    }

    public function getOrderNumber()
    {
        return $this->base['receive_order_shop_cut_form_id'];
    }

    // 伝票の各ステータスを調べるインスタンスメソッド群
    // input:  void
    // output: boolean
    // 新規受付のステータスか判定する関数
    public function isNewStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_IMPORT_ORDER_MAIL &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED);
    }

    // 確認待ち
    public function isConfirmWaitStatus()
    {
        return ($this->base['receive_order_confirm_check_id'] === self::NEED_CONFIRM &&
               ($this->base['receive_order_order_status_id']  === self::ORDER_STATUS_LACK_OF_INFORMATION || $this->base['receive_order_order_status_id'] === self::ORDER_STATUS_ISSUING_PAYMENT_SLIP) &&
                $this->base['receive_order_cancel_type_id']   === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']     !== self::DELETED);
    }

    // 入金待ち
    public function isDepositWaitStatus()
    {
        // もし支払方法が「代金引換」「銀行振込後払い」「サンプル・貸し出し」「掛売」であれば「未入金」でも入金待ち判定からは除外
        // ここはお客様の環境毎、店舗毎に異なる場合がございます
        // デフォルト値を想定して設定しています
        if ($this->base['receive_order_payment_method_id'] === self::PAYMENT_CASH_ON_DELIVERY ||
            $this->base['receive_order_payment_method_id'] === self::PAYMENT_LATER_PAYMENT ||
            $this->base['receive_order_payment_method_id'] === self::PAYMENT_SAMPLE_OR_LENDING ||
            $this->base['receive_order_payment_method_id'] === self::PAYMENT_SELLING_ON_CREDIT) {
             return false;
        }

        // 「入金済み」の状態以外は入金待ちの判定にする
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_ISSUING_PAYMENT_SLIP &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deposit_type_id'] !== self::DEPOSITED &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED);
    }

    // 引当待ち
    public function isAllocationWaitStatus()
    {
        if ($this->base['receive_order_order_status_id'] !== self::ORDER_STATUS_ISSUING_PAYMENT_SLIP ||
            $this->base['receive_order_cancel_type_id']  !== self::AVAILABLE_ORDER ||
            $this->base['receive_order_deleted_flag']    === self::DELETED) {
             return false;
        }
        // 明細の受注数と引当数をチェックする
        foreach ($this->details as $detail) {
            if ($detail['receive_order_row_stock_allocation_quantity'] !== $detail['receive_order_row_quantity']) {
                // 1つでも異なるものがあれば引当待ち
                return true;
            }
        }
        // 明細全ての受注数と引当数が一致していたら引当待ちではない
        return false;
    }

    // 発売日待ち
    public function isReleaseWaitStatus(array $beforeReleaseGoods)
    {
        if ($this->base['receive_order_order_status_id'] !== self::ORDER_STATUS_ISSUING_PAYMENT_SLIP ||
            $this->base['receive_order_cancel_type_id']  !== self::AVAILABLE_ORDER ||
            $this->base['receive_order_deleted_flag']    === self::DELETED) {
             return false;
        }
        foreach ($this->details as $detail) {
            foreach ($beforeReleaseGoods as $goods) {
                if ($goods['goods_id'] === $detail['receive_order_row_goods_id']) {
                    // 発売日が今日より未来の商品があれば発売日待ち
                    return true;
                }
            }
        }
        return false;
    }

    // 印刷日待ち
    public function isPrintingDateWaitStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_PRINT_WAIT &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED &&
                $this->base['receive_order_statement_delivery_instruct_printing_date'] >= date('Y-m-d H:i:s'));
    }

    // 印刷待ち
    public function isPrintingWaitStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_PRINT_WAIT &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED &&
                $this->base['receive_order_statement_delivery_instruct_printing_date'] <= date('Y-m-d H:i:s'));
    }

    // 印刷済み
    public function isPrintedStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_PRINTED &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED);
    }

    // 出荷済み
    public function isSendStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_DISPATCH &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_deleted_flag']    !== self::DELETED &&
                $this->base['receive_order_send_date'] >= date('Y-m-d 00:00:00') &&
                $this->base['receive_order_send_date'] <= date('Y-m-d 23:59:59'));
    }

    // 出荷済み（処理済みでどのステータスにも属していない状態）
    public function isFinishStatus()
    {
        return ($this->base['receive_order_order_status_id'] === self::ORDER_STATUS_DISPATCH &&
                $this->base['receive_order_cancel_type_id']  === self::AVAILABLE_ORDER &&
                $this->base['receive_order_send_date'] <= date('Y-m-d 00:00:00', strtotime('-1 day')));
    }
}
