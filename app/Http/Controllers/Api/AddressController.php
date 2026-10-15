<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $key = config('services.geoapify.key');

        if (! $key) {
            return response()->json(['message' => 'Las sugerencias todavía no están configuradas. Puedes escribir la dirección manualmente.'], 503);
        }

        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(6)->get(
                'https://api.geoapify.com/v1/geocode/autocomplete',
                [
                    'text' => trim($data['q']),
                    'filter' => 'countrycode:pe',
                    'lang' => 'es',
                    'limit' => 5,
                    'format' => 'json',
                    'apiKey' => $key,
                ]
            );
        } catch (ConnectionException) {
            return response()->json(['message' => 'El servicio de direcciones no responde. Puedes escribir la dirección manualmente.'], 502);
        }

        if (! $response->successful() || ! is_array($response->json('results'))) {
            return response()->json(['message' => 'No se pudieron obtener las sugerencias. Puedes escribir la dirección manualmente.'], 502);
        }

        $addresses = collect($response->json('results'))
            ->filter(fn ($address): bool => is_array($address) && ($address['country_code'] ?? '') === 'pe')
            ->map(function (array $address): array {
                $line = $address['address_line1'] ?? $address['name'] ?? trim(($address['street'] ?? '').' '.($address['housenumber'] ?? ''));
                $district = $address['city'] ?? $address['city_district'] ?? $address['district'] ?? '';
                $label = implode(', ', array_unique(array_filter([$line, $district])));

                return ['name' => $line, 'label' => $label];
            })
            ->filter(fn (array $address): bool => $address['name'] !== '' && mb_strlen($address['label']) <= 255)
            ->unique('label')->take(5)->values();

        return response()->json($addresses);
    }
}
