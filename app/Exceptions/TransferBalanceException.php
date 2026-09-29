<?php

namespace App\Exceptions;

/**
 * T26: يُرمى عندما لا يكفي رصيد عهدة المُرسل لإتمام تحويل معلق عند القبول
 * (مثلاً تحويل قديم أُنشئ قبل التجميد). عند التقاطه يُرسل إشعار للمُرسل بالسبب.
 */
class TransferBalanceException extends \Exception
{
}
