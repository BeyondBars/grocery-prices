<?php

namespace Illuminate\Http {
    /**
     * @method public bool isTwoFactorVerified() Determine if the user for this request is two factor verified.
     * @method public bool isUsingSanctum() Determine if the user for this request is two factor verified.
     * @method public bool hasValidAppSignature() Determines if the request has a valid application signature with given parameters.
     * @see \Illuminate\Http\Request
     * @see \App\Support\Mixins\RequestMixin
     */
    class Request
    {
    }
}

namespace Illuminate\Routing {
    /**
     * @method public string app(string $path = null) Generate a URL to the frontend application with an optional path.
     * @method public string appSigned(string $path, array $signatureParameters, $expires = null, bool $public = false) Generates a signed URL to the frontend application.
     * @method public string appSignature(array $signatureParameters, $expires = null) Generates a signature for the given parameters and expiration time.
     * @method public bool hasValidAppSignature(\Illuminate\Http\Request $request, array $signatureParameters) Determines if the request has a valid signature for the given parameters.
     */
    class UrlGenerator
    {
    }
}

namespace Illuminate\Support\Facades {
    /**
     * @method static string app(string $path = null) Generate a URL to the frontend application with an optional path.
     * @method static string appSigned(string $path, array $signatureParameters, $expires = null, bool $public = false) Generates a signed URL to the frontend application.
     * @method static string appSignature(array $signatureParameters, $expires = null) Generates a signature for the given parameters and expiration time.
     * @method static bool hasValidAppSignature(\Illuminate\Http\Request $request, array $signatureParameters) Determines if the request has a valid signature for the given parameters.
     */
    class URL
    {
    }
}
