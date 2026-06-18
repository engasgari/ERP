<x-erp.ui.data-table :headers="$headers" :empty-message="$emptyMessage">
    @foreach($rows as $row)
        <tr>
            @foreach($row as $cell)
                <td>{!! $cell !!}</td>
            @endforeach
        </tr>
    @endforeach
</x-erp.ui.data-table>
