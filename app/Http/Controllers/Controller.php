<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * استجابة موحدة لنماذج المودال عبر AJAX (T25):
     * إذا كان الطلب يتوقع JSON تُعاد {success, message}، وإلا يُعاد السلوك الحالي (redirect مع flash)
     * $type: success | error | warning ...
     */
    protected function formBackOrJson(Request $request, string $type, string $message, bool $withInput = false)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $type === 'success',
                'message' => $message,
            ], $type === 'success' ? 200 : 422);
        }

        $back = $withInput ? back()->withInput() : back();
        return $back->with($type, $message);
    }
}
