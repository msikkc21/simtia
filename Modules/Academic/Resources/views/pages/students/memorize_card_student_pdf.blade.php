@inject('helpers', 'Modules\Academic\Http\Controllers\AcademicController')
@php
    $logo =
        strpos($profile['logo'], 'img') > 0
            ? str_replace(url(''), base_path() . '/public', $profile['logo'])
            : str_replace(url('') . '/storage', base_path() . '/storage/app/public/', $profile['logo']);
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') . ' ' . strtoupper(Session::get('institute')) }} - DATA HAFALAN SANTRI</title>
    <link href="file:///{{ public_path('css/print-minimal.css') }}" rel="stylesheet" />
</head>

<body>
    <div id="header">
        <table class="table no-border" style="width:100%;">
            <tbody>
                <tr>
                    <th rowspan="2" width="100px"><img src="file:///{{ $logo }}" height="80px" /></th>
                    <td><b>{{ strtoupper($profile['name']) }}</b></td>
                </tr>
                <tr>
                    <td style="font-size:11px;">
                        {{ $profile['address'] }}<br />
                        Telpon: {{ $profile['phone'] }} - Faksimili: {{ $profile['fax'] }}<br />
                        Website: {{ $profile['web'] }} - Email: {{ $profile['email'] }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <hr />
    <div id="body">
        <br />
        <div class="text-center" style="font-size:16px;"><b>DATA HAFALAN SANTRI</b></div>
        <br />
        <br />
        <div>
            <table class="table no-border" style="font-size: 13px;font-weight:700">
                <tbody>
                    <tr>
                        <td style="width:3%;">Departemen</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td style="width:30%;">{{ $requests->department }}</td>
                        <td style="width:3%;">NIS</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td style="width:30%;">{{ $requests->student_no }}</td>
                    </tr>
                    <tr>
                        <td style="width:3%;">Tahun Ajaran</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td>{{ $requests->schoolyear }}</td>
                        <td style="width:3%;">Nama</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td style="width:30%;">{{ $requests->student_name }}</td>
                    </tr>
                    <tr>
                        <td style="width:3%;">Tingkat/Semester</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td>{{ $requests->grade . '/' . $requests->semester }}</td>
                        <td style="width:3%;">Kelas</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td style="width:30%;">{{ $requests->class }}</td>
                    </tr>
                    <tr>
                        <td style="width:3%;">Bulan</td>
                        <td style="width: 1%;text-align:center;">:</td>
                        <td colspan="4">
                            {{ $requests->month_name ?? '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <br />
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th class="text-center" rowspan="2" width="5%">No.</th>
                        <th class="text-center" rowspan="2" width="10%">Tanggal</th>
                        <th class="text-center" colspan="2">Dari</th>
                        <th class="text-center" colspan="2">Sampai</th>
                        <th class="text-center" rowspan="2">Status</th>
                    </tr>
                    <tr>
                        <th class="text-center">Surat</th>
                        <th class="text-center">Ayat</th>
                        <th class="text-center">Surat</th>
                        <th class="text-center">Ayat</th>
                    </tr>
                </thead>
                <tbody>
                    @php $num = 1; @endphp
                    @if(isset($requests->memorizations) && count($requests->memorizations) > 0)
                        @foreach ($requests->memorizations as $memorize)
                            @php
                                $from_surah = $helpers->getSurah($memorize->from_surah ?? null);
                                $to_surah = $helpers->getSurah($memorize->to_surah ?? null);
                            @endphp
                            <tr>
                                <td class="text-center">{{ $num++ }}</td>
                                <td class="text-center">{{ isset($memorize->memorize_date) ? $helpers->formatDate($memorize->memorize_date, 'local') : '-' }}</td>
                                <td class="text-center">
                                    {{ $from_surah->id > 0 ? sprintf('%03d', $from_surah->id) . ' - ' . $from_surah->surah : '-' }}
                                </td>
                                <td class="text-center">{{ $memorize->from_verse ?? '-' }}</td>
                                <td class="text-center">
                                    {{ $to_surah->id > 0 ? sprintf('%03d', $to_surah->id) . ' - ' . $to_surah->surah : '-' }}
                                </td>
                                <td class="text-center">{{ $memorize->to_verse ?? '-' }}</td>
                                <td class="text-center">{{ $memorize->status ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td class="text-center" colspan="7">Tidak ada data hafalan pada bulan ini</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
