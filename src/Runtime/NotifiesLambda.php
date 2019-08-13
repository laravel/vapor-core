<?php

namespace Laravel\Vapor\Runtime;

use Exception;

trait NotifiesLambda
{
    /**
     * Send the response data to Lambda.
     *
     * @param  string  $invocationId
     * @param  mixed  $data
     * @return void
     */
    protected function notifyLambdaOfResponse($invocationId, $data)
    {
        return $this->lambdaRequest(
            "http://{$this->apiUrl}/2018-06-01/runtime/invocation/{$invocationId}/response", $data
        );
    }

    /**
     * Send the error response data to Lambda.
     *
     * @param  string  $invocationId
     * @param  mixed  $data
     * @return void
     */
    protected function notifyLambdaOfError($invocationId, $data)
    {
        return $this->lambdaRequest(
            "http://{$this->apiUrl}/2018-06-01/runtime/invocation/{$invocationId}/error", $data
        );
    }

    /**
     * Send the given data to the given URL as JSON.
     *
     * @param  string  $url
     * @param  mixed  $data
     * @return void
     */
    protected function lambdaRequest($url, $data)
    {
        $json = json_encode($data);

        if ($json === false) {
            throw new Exception('Error encoding runtime JSON response: '.json_last_error_msg());
        }

        $handler = curl_init($url);

        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $json);

        curl_setopt($handler, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: '.strlen($json),
        ]);

        curl_exec($handler);

        if (curl_error($handler)) {
            $errorMessage = curl_error($handler);

            throw new Exception('Error calling the runtime API: ' . $errorMessage);
        }

        curl_setopt($handler, CURLOPT_HEADERFUNCTION, null);
        curl_setopt($handler, CURLOPT_READFUNCTION, null);
        curl_setopt($handler, CURLOPT_WRITEFUNCTION, null);
        curl_setopt($handler, CURLOPT_PROGRESSFUNCTION, null);

        curl_reset($handler);

        curl_close($handler);
    }
}
