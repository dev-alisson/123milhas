<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FlightController
 * @package App\Http\Controllers
 */
class FlightController extends Controller
{
    /**
     * All
     * Retorna no formato JSON todos
     * os voos separados em grupos
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function all(): \Illuminate\Http\JsonResponse
    {
        $response = Http::get('http://prova.123milhas.net/api/flights');
        $flightGroups = $this->flightsByFare($response->json());

        return response()->json($flightGroups, Response::HTTP_OK);
    }

    /**
     * Groups Of Fare
     * Retorna os tipos de tarifa
     *
     * @param array $flights
     * @return array
     */
    public function groupsOfFare(array $flights): array
    {
        $groupsOfFare = [];

        foreach ($flights as $flight) {
            if (!in_array($flight['fare'], $groupsOfFare)) {
                $groupsOfFare[] = $flight['fare'];
            }
        }

        return $groupsOfFare;
    }

    /**
     * Flights By Fare
     * Separa os voos por tipo de tarifa
     *
     * @param array $flights
     * @return array
     */
    public function flightsByFare(array $flights): array
    {
        /*
         * Deleta os voos que não possuírem o
         * tipo de tarifa, evitando assim erros
         */
        $flights = $this->cleanEmptyFlights($flights);

        /*
         * Variáveis a serem utilizadas no processo
         * de separação e agrupamento dos grupos
         */
        $groupsOfFare = $this->groupsOfFare($flights);
        $totalOfGroups = count($groupsOfFare);
        $totalFlights = count($flights);
        $flightsByFare = [];

        /*
         * Armazena no formato array() os voos
         * do tipo de tarifa do índice {$fare}
         */
        foreach ($groupsOfFare as $key => $fare) {
            // Gera o identificador do grupo
            $id = $key + 1;

            /*
             * Agrupa os voos por tarifa
             * referente ao índice {$fare}
             */
            $flightGroup = [
                'uniqueId' => $id,
                'totalPrice' => 0,
                'outbound' => [],
                'inbound' => []
            ];

            foreach ($flights as $flight) {
                if ($flight['fare'] == $fare) {
                    $flightGroup[$this->typeOfFlight($flight)][] = $flight;
                    $flightGroup['totalPrice'] += $flight['price'];
                }
            }

            $flightsByFare[] = $flightGroup;
        }

        // Ordena os grupos pelo preço mais barato
        $flightsByFare = $this->sortByKey($flightsByFare, 'totalPrice');

        // Retorna o preço e o ID do grupo de menor preço
        $cheapestGroup = $this->cheapestGroup($flightsByFare);

        /*
         * Retorna no formato array() os dados
         * para serem listados no método All()
         */
        return [
            'flights' => $flights,
            'groups' => $flightsByFare,
            'totalGroups' => $totalOfGroups,
            'totalFlights' => $totalFlights,
            'cheapestPrice' => $cheapestGroup['totalPrice'],
            'cheapestGroup' => $cheapestGroup['uniqueId']
        ];
    }

    /**
     * Type Of Flight
     * Retorna se o voo é ida ou volta
     *
     * Se o voo for de ida: Retorna 'outbound'
     * Se o voo for de volta: Retorna 'inbound'
     *
     * @param array $flight
     * @return string
     */
    public function typeOfFlight(array $flight): string
    {
        return ($flight['outbound'] ? 'outbound' : 'inbound');
    }

    /**
     * Cheapest Group
     * Retorna no formato array() o preço
     * e o ID do grupo de menor preço
     *
     * @param array $groups
     * @return array
     */
    public function cheapestGroup(array $groups): array
    {
        $cheapestGroup = [
            'uniqueId' => $groups[0]['uniqueId'],
            'totalPrice' => $groups[0]['totalPrice']
        ];

        foreach ($groups as $group) {
            if ($cheapestGroup['totalPrice'] > $group['totalPrice']) {
                $cheapestGroup['uniqueId'] = $group['uniqueId'];
                $cheapestGroup['totalPrice'] = $group['totalPrice'];
            }
        }

        return $cheapestGroup;
    }

    /**
     * Sort By Key
     * Ordena um array() pela {$key}
     * informada de forma crescente
     *
     * @param array $data
     * @param string $key
     * @return array
     */
    public function sortByKey(array $data, string $key): array
    {
        array_multisort(
            array_map(
                function ($item) use ($key) {
                    return $item[$key];
                },
                $data
            ),
            SORT_ASC,
            $data
        );

        return $data;
    }

    /**
     * Clean Empty Flights
     * Deleta os voos que não possuir as informações
     * necessárias para entrar em um grupo
     *
     * @param array $flights
     * @return array
     */
    public function cleanEmptyFlights(array $flights): array
    {
        $cleanEmptyFlights = [];

        foreach ($flights as $flight) {
            if (in_array('fare', $flight)) {
                $cleanEmptyFlights[] = $flight;
            }
        }

        return $cleanEmptyFlights;
    }
}
