<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApartmentController extends Controller
{
    public function index(Request $request)
    {
        // Ottieni la data e l'ora attuali
        $now = Carbon::now();

        // Eager loading per caricare le relazioni sponsorizzate
        $apartments = Apartment::with('sponsorships', 'services')
            ->where('is_visible', 1) // Considera solo gli appartamenti visibili
            ->orderByRaw('CASE 
            WHEN id IN (SELECT apartment_id FROM apartment_sponsorship WHERE end_date > ?) THEN 0
            ELSE 1
        END, created_at DESC', [$now])
            ->get();

        return response()->json($apartments);
    }



    public function search(Request $request)
    {
        $data = $request->validate([
            'address' => 'required|string',
            'rooms' => 'nullable|numeric',
            'beds' => 'nullable|numeric',
            'bathrooms' => 'nullable|numeric',
            'radius' => 'nullable|numeric',
            'services.*' => 'nullable|exists:services,id',
        ]);

        $address = $data['address'];

        // Verifica se il parametro radius è stato fornito
        $radius = $request->has('radius') ? $data['radius'] : 5;

        // Effettua una chiamata all'API di geocodifica per ottenere la latitudine e la longitudine
        $response = Http::withoutVerifying()->get('https://api.tomtom.com/search/2/geocode/' . urlencode($address) . '.json', [
            'key' => env('TOMTOM_API_KEY'),
        ]);

        if ($response->successful()) {
            $addressQuery = $response->json();
            if (isset($addressQuery['results']) && !empty($addressQuery['results'])) {
                $latitude = $addressQuery['results'][0]['position']['lat'];
                $longitude = $addressQuery['results'][0]['position']['lon'];

                // Calcolo della distanza per appartamenti sponsorizzati e non sponsorizzati
                $sponsoredApartments = Apartment::with('services', 'sponsorships')
                    ->whereHas('sponsorships', function ($query) {
                        $query->where('end_date', '>', now());
                    })
                    ->get()
                    ->map(function ($apartment) use ($latitude, $longitude) {
                        $apartment->distance = $this->haversineDistance($latitude, $longitude, $apartment->latitude, $apartment->longitude);
                        return $apartment;
                    })
                    ->sortBy('distance');

                $nonSponsoredApartments = Apartment::with('services', 'sponsorships')
                    ->whereDoesntHave('sponsorships', function ($query) {
                        $query->where('end_date', '>', now());
                    })
                    ->get()
                    ->map(function ($apartment) use ($latitude, $longitude) {
                        $apartment->distance = $this->haversineDistance($latitude, $longitude, $apartment->latitude, $apartment->longitude);
                        return $apartment;
                    })
                    ->sortBy('distance');

                // Unione ordinata degli appartamenti
                $mergedApartments = $sponsoredApartments->concat($nonSponsoredApartments);

                return response()->json($mergedApartments);
            } else {
                // Nessun risultato trovato per l'indirizzo fornito
                return response()->json(['error' => 'Indirizzo non valido'], 400);
            }
        } else {
            // La chiamata all'API non è riuscita
            return response()->json(['error' => 'Errore durante la geocodifica'], 500);
        }
    }

    // Funzione per il calcolo della distanza tramite la formula di Haversine
    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }






    public function show(Request $request)
    {
        // Assicurati che l'ID sia presente nella richiesta
        if ($request->has('id')) {
            // Recupera l'ID dalla richiesta
            $id = $request->input('id');

            // Esegui la query utilizzando l'ID sulla tabella "apartments"
            $apartment = Apartment::with('sponsorships', 'services')->find($id);

            // Verifica se l'appartamento è stato trovato
            if ($apartment) {
                // Ritorna l'appartamento (puoi fare altro con esso qui)
                return response()->json($apartment);
            } else {
                // Nel caso in cui l'appartamento non sia stato trovato, restituisci un messaggio di errore
                return response()->json(['error' => 'Appartamento non trovato'], 404);
            }
        } else {
            // Se l'ID non è presente nella richiesta, restituisci un messaggio di errore
            return response()->json(['error' => 'ID mancante nella richiesta'], 400);
        }
    }
    public function sponsored()
    {
        // Ottieni la data e l'ora attuali
        $now = Carbon::now();

        // Ottieni gli appartamenti sponsorizzati
        $sponsoredApartments = Apartment::whereHas('sponsorships', function ($query) use ($now) {
            // Filtra le sponsorizzazioni attive
            $query->where('end_date', '>', $now);
        })
            ->where('is_visible', 1) // Aggiungi la condizione per is_visible
            ->with(['sponsorships' => function ($query) {
                // Ordina le sponsorizzazioni per data di creazione decrescente
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy(function ($query) {
                // Ordina gli appartamenti in base alla data di creazione della sponsorizzazione più recente
                $query->select('created_at')
                    ->from('apartment_sponsorship')
                    ->whereColumn('apartment_id', 'apartments.id')
                    ->orderBy('created_at', 'desc')
                    ->limit(1);
            }, 'desc')
            ->take(6) // Prendi solo i primi 6 appartamenti sponsorizzati
            ->get();

        return response()->json($sponsoredApartments);
    }
}
