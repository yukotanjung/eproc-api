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

    function errorResponses($result = [], $message = "Gagal memproses permintaan.")
    {
        if (is_string($result)) {
            $message = $result;
            $result = [];
        } elseif (is_object($result) && method_exists($result, 'first')) {
            $firstMessage = $result->first();
            if (!empty($firstMessage)) {
                $message = $firstMessage;
            }
        } elseif (is_array($result) && !empty($result)) {
            foreach ($result as $value) {
                $firstMessage = is_array($value) ? ($value[0] ?? null) : $value;
                if (!empty($firstMessage) && is_string($firstMessage)) {
                    $message = $firstMessage;
                    break;
                }
            }
        }

        return response()->json([
            "status" => false,
            "message" => $message,
            "result" => $result
        ], 400);
    }

    function failedActionResponse(\Throwable $e, string $defaultMessage = "Gagal memproses data.", int $code = 403)
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return $this->errorResponses($e->validator->getMessageBag());
        }

        if ($e instanceof \Illuminate\Database\QueryException) {
            $raw = strtolower($e->getMessage());
            if (str_contains($raw, 'ora-00001') || str_contains($raw, 'unique')) {
                return $this->invalidResponse($defaultMessage . ' Data sudah terdaftar di sistem.', $code);
            }
            if (str_contains($raw, 'ora-02292') || str_contains($raw, 'ora-02291') || str_contains($raw, 'foreign key')) {
                return $this->invalidResponse($defaultMessage . ' Data masih digunakan oleh data lain.', $code);
            }
        }

        return $this->invalidResponse($defaultMessage, $code);
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
