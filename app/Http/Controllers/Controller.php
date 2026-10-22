<?php

namespace App\Http\Controllers;

abstract class Controller
{
    function baseResponse(string $message, mixed $result = null, $paginate = null)
    {
        $response = [
            'message' => $message,
            'result' => $result,
        ];

        if ($paginate !== null) {
            $response['paginate'] = $paginate;
        }

        return response()->json($response, 201);
    }

    function mobileResponse(string $message, mixed $result, string $clear)
    {
        $response = [
            'message' => $message,
            'clear_trash' => $clear,
            'result' => $result
        ];


        return response()->json($response, 201);
    }

    function invalidResponse(string $message, int $code = 403)
    {
        $response = [
            'message' => $message,
        ];
        return response()->json($response, $code);
    }

    function errorValidateResponse(string $message, mixed $result = null, int $code = 400)
    {
        $response = [
            'message' => $message,
            'result' => $result
        ];
        return response()->json($response, $code);
    }

    function errorResponses($result = [], $message = "Failed")
    {
        if (is_string($result)) {
            $message = $result;
            $result = [];
        } else {
            $tmp = $result;
            $message = $tmp->first();
            if (!empty($tmp)) {
                if (count($tmp) > 0) {
                    foreach ($tmp as $key => $value) {
                        $message = "Error Validation on " . $key . " : " . $value[0];
                        break;
                    }
                }
            }
        }
        return response()->json([
            "status" => false,
            "message" => $message,
            "result" => $result
        ], 400);
    }

    public function pagination($data)
    {
        return [
            'total' => $data->total(),
            'per_page' => $data->perPage(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
        ];
    }
}
