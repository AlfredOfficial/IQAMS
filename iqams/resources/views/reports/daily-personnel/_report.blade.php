<section class="attendance-document" aria-label="Daily personnel attendance report">
    <header class="report-heading">
        <h2>DANAO TECHNOLOGICAL COLLEGE</h2>
        <h3>DAILY ATTENDANCE REPORT</h3>
        <p>Date: {{ $date->format('F j, Y') }}</p>
    </header>

    <table class="attendance-table">
        <thead>
            <tr>
                <th rowspan="2" class="name-column">Name</th>
                <th colspan="2">MORNING</th>
                <th colspan="2">AFTERNOON</th>
            </tr>
            <tr>
                <th>TIME-IN</th>
                <th>TIME-OUT</th>
                <th>TIME-IN</th>
                <th>TIME-OUT</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['morning_time_in'] }}</td>
                    <td>{{ $row['morning_time_out'] }}</td>
                    <td>{{ $row['afternoon_time_in'] }}</td>
                    <td>{{ $row['afternoon_time_out'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-report">No active personnel match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer class="report-signatures">
        <p>Prepared by: <span></span></p>
        <p>Checked by: <span></span></p>
        <p>Date: <span class="date-line"></span></p>
    </footer>
</section>
