@extends('layouts.admin')

@section('title', 'Analytics')

@section('content')
    <div class="container py-4">
        <h1 class="pb-3">Analytics</h1>
        <div class="row">
            <div class="col-md-4">
                <h2 class="text-center mb-3">Filter by Apartment and Date Range</h2>
                <form id="filterForm">
                    @csrf <!-- Aggiungi questa direttiva per proteggere il form -->
                    <div class="mb-3">
                        <label for="apartmentSelect" class="form-label">Select Apartment</label>
                        <select class="form-select" id="apartmentSelect" name="apartmentId">
                            <option value="">Select Apartment</option>
                            @foreach ($apartments as $apartment)
                                <option value="{{ $apartment->id }}">{{ $apartment->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="startDate" name="startDate">
                    </div>
                    <div class="mb-3">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="endDate" name="endDate">
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <!-- Aggiunta del div per il messaggio di avviso -->
                    <div id="missingFieldMessage" class="alert alert-danger mt-3" style="display: none;">Please fill all the
                        fields.</div>
                </form>
            </div>
            <div class="col-md-8">
                <h2 class="text-center mb-3">Analytics Chart</h2>
                <div id="chartContainer" style="position: relative;">
                    <canvas id="myChart" style="display: none;"></canvas>
                    <div id="emptyChartDataMessage" class="alert alert-info" style="display: none;">{{ $apartment->title }}
                        has no data available for
                        the chart.</div>
                </div>
                <div id="emptyDataMessage" class="alert alert-warning" style="display: none;">Please select both start and
                    end dates.</div>
            </div>
        </div>
    </div>

    <!-- Assicurati di includere jQuery, Chart.js e Axios.js prima di utilizzarli -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        $(document).ready(function() {
            const ctx = document.getElementById('myChart').getContext('2d');
            let myChart = new Chart(ctx, {
                type: 'line',
                data: {},
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Funzione per generare le date tra due date
            function getAllDates(startDate, endDate) {
                const dates = [];
                let currentDate = new Date(startDate);
                const end = new Date(endDate);

                while (currentDate <= end) {
                    dates.push(new Date(currentDate));
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                return dates;
            }

            // Funzione per aggiornare il grafico in base al filtro di data
            function updateChart(apartmentId, startDate, endDate) {
                axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')
                    .getAttribute('content');


                axios.get('http://localhost:8000/api/analytics', {
                        params: {
                            apartmentId: apartmentId,
                            startDate: startDate,
                            endDate: endDate
                        }
                    })
                    .then(function(response) {
                        const data = response.data;
                        const allDates = getAllDates(startDate, endDate);
                        const visitsData = data.visits || [];
                        const messagesData = data.messages || [];

                        // Aggiorna il grafico con i nuovi dati solo se sono definiti
                        if ((visitsData.length > 0 || messagesData.length > 0) && startDate && endDate) {
                            myChart.destroy();
                            myChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: allDates.map(date => date.toLocaleDateString()),
                                    datasets: [{
                                        label: 'Visits',
                                        data: allDates.map(date => {
                                            const visit = visitsData.find(item => item
                                                .date === date.toISOString().split(
                                                    'T')[0]);
                                            return visit ? visit.count : 0;
                                        }),
                                        fill: false,
                                        borderColor: 'rgba(75, 192, 192, 1)',
                                        tension: 0.1
                                    }, {
                                        label: 'Messages',
                                        data: allDates.map(date => {
                                            const message = messagesData.find(item =>
                                                item.date === date.toISOString()
                                                .split('T')[0]);
                                            return message ? message.count : 0;
                                        }),
                                        fill: false,
                                        borderColor: 'rgba(255, 99, 132, 1)',
                                        tension: 0.1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                            // Visualizza il grafico e nasconde i messaggi di avviso
                            $('#myChart').show();
                            $('#emptyDataMessage').hide();
                            $('#emptyChartDataMessage').hide();
                            $('#missingFieldMessage').hide(); // Nasconde il messaggio di campo mancante
                        } else if (!startDate || !endDate) {
                            // Se uno o entrambi i campi delle date sono vuoti, mostra un messaggio di avviso
                            // specifico per i campi delle date vuote
                            $('#myChart').hide();
                            $('#emptyDataMessage').show();
                            $('#emptyChartDataMessage').hide();
                        } else {
                            // Se non ci sono dati disponibili per il grafico, mostra un messaggio di avviso
                            // specifico per i dati del grafico vuoti
                            $('#myChart').hide();
                            $('#emptyDataMessage').hide();
                            $('#emptyChartDataMessage').show();
                        }
                    })
                    .catch(function(error) {
                        console.error('Error retrieving statistics data', error);
                    });
            }

            // Mostra il grafico vuoto all'apertura della pagina
            updateChart(null, null, null);

            $('#filterForm').submit(function(e) {
                e.preventDefault();
                const apartmentId = $('#apartmentSelect').val();
                const startDate = $('#startDate').val();
                const endDate = $('#endDate').val();

                // Verifica se uno dei campi è vuoto
                if (!apartmentId || !startDate || !endDate) {
                    $('#missingFieldMessage').show();
                    return;
                }

                // Aggiorna il grafico quando il modulo viene inviato
                updateChart(apartmentId, startDate, endDate);
            });

            // Nascondi il messaggio di campo mancante quando si inseriscono dati in uno dei campi di ricerca
            $('#apartmentSelect, #startDate, #endDate').on('input', function() {
                $('#missingFieldMessage').hide();
            });
        });
    </script>
    {{-- fine script --}}

@endsection
