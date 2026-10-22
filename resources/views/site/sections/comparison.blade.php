<div class="table-scroll">
    <table class="compare">
        <thead><tr><th scope="col">Capability</th><th scope="col">{{ $data['us_label'] ?? 'Composa' }}</th><th scope="col">{{ $data['them_label'] ?? 'Typical options app' }}</th></tr></thead>
        <tbody>
            @foreach ($data['rows'] ?? [] as $row)
                <tr><td>{{ $row['feature'] }}</td><td>{{ $row['us'] }}</td><td>{{ $row['them'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
