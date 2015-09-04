<?php
/**
 * API通信時の例外を扱うクラス
 * NE-APIのcodeは'000001'等の文字列なので通常のExceptionクラスでは扱えないため、
 * NE-API用の独自の例外クラスを実装した
 */
class NeApiException extends Exception
{
    protected $message;
    protected $code;

    public function __construct($message, $code)
    {
        parent::__construct($message);

        $this->message = $message;
        $this->code    = $code;
    }
}