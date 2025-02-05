<?php

namespace MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms;

use Illuminate\Http\Request;

class RequestFingerprintGenerator extends UniqueKeyAlgorithm
{
    private string $key;

    public function name(): string
    {
        return 'request_fingerprint';
    }

    public function handle(Request $request): string
    {
        if (isset($this->key)) {
            return $this->key;
        }

        $input = $request->input();

        if ($files = $request->allFiles()) {
            foreach ($files as $key => $file) {
                /** @var UploadedFile $file */
                $input[$key] = $file->getClientOriginalName().$file->getSize();
            }
        }
        $input['url'] = $request->fullUrl();

        $this->key = hash('sha256', serialize($input));

        return $this->key;
    }
}
