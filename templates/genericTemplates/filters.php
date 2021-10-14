<?php
use Cake\Utility\Inflector;

$filteringForm = $this->Bootstrap->table(
    [
        'small' => true,
        'striped' => false,
        'hover' => false,
        'tableClass' => ['indexFilteringTable'],
    ],
    [
    'fields' => [
        __('Field'),
        __('Operator'),
        [
            'labelHtml' => sprintf('%s %s',
                __('Value'),
                sprintf('<sup class="fa fa-info" title="%s"><sup>', __('Supports strict matches and LIKE matches with the `%` character.&#10;Example: `%.com`'))
            )
        ],
        __('Action')
    ],
    'items' => []
]);

if ($taggingEnabled) {
    $helpText = $this->Bootstrap->genNode('sup', [
        'class' => ['ms-1 fa fa-info'],
        'title' => __('Supports negation matches (with the `!` character) and LIKE matches (with the `%` character).&#10;Example: `!exportable`, `%able`'),
        'data-bs-toggle' => 'tooltip',
    ]);
    $filteringTags = $this->Bootstrap->genNode('h5', [], __('Tags') . $helpText);
    $filteringTags .= $this->Tag->tags([], [
        'allTags' => $allTags,
        'picker' => true,
        'editable' => false,
    ]);
} else {
    $filteringTags = '';
}
$modalBody = sprintf('%s%s', $filteringForm, $filteringTags);


echo $this->Bootstrap->modal([
    'title' => __('Filtering options for {0}', Inflector::singularize($this->request->getParam('controller'))),
    'size' => 'lg',
    'type' => 'confirm',
    'bodyHtml' => $modalBody,
    'confirmText' => __('Filter'),
    'confirmFunction' => 'filterIndex'
]);
?>

<script>
    $(document).ready(() => {
        const $filteringTable = $('table.indexFilteringTable')
        initFilteringTable($filteringTable)
    })

    function filterIndex(modalObject, tmpApi) {
        const controller = '<?= $this->request->getParam('controller') ?>';
        const action = 'index';
        const $tbody = modalObject.$modal.find('table.indexFilteringTable tbody')
        const $rows = $tbody.find('tr:not(#controlRow)')
        const activeFilters = {}
        $rows.each(function() {
            const rowData = getDataFromRow($(this))
            let fullFilter = rowData['name']
            if (rowData['operator'] == '!=') {
                fullFilter += ' !='
            }
            activeFilters[fullFilter] = rowData['value']
        })
        $select = modalObject.$modal.find('select.tag-input')
        activeFilters['filteringTags'] = $select.select2('data').map(tag => tag.text)
        const searchParam = jQuery.param(activeFilters);
        const url = `/${controller}/${action}?${searchParam}`

        const randomValue = getRandomValue()
        UI.reload(url, $(`#table-container-${randomValue}`), $(`#table-container-${randomValue} table.table`), [{
            node: $(`#toggleFilterButton-${randomValue}`),
            config: {}
        }])
    }

    function initFilteringTable($filteringTable) {
        const $controlRow = $filteringTable.find('#controlRow')
        $filteringTable.find('tbody').empty()
        addControlRow($filteringTable)
        const randomValue = getRandomValue()
        const activeFilters = Object.assign({}, $(`#toggleFilterButton-${randomValue}`).data('activeFilters'))
        const tags = activeFilters['filteringTags'] !== undefined ? Object.assign({}, activeFilters)['filteringTags'] : []
        delete activeFilters['filteringTags']
        for (let [field, value] of Object.entries(activeFilters)) {
            const fieldParts = field.split(' ')
            let operator = '='
            if (fieldParts.length == 2 && fieldParts[1] == '!=') {
                operator = '!='
                field = fieldParts[0]
            } else if (fieldParts.length > 2) {
                console.error('Field contains multiple spaces. ' + field)
            }
            addFilteringRow($filteringTable, field, value, operator)
        }
        $select = $filteringTable.closest('.modal-body').find('select.tag-input')
        let passedTags = []
        tags.forEach(tagname => {
            const existingOption = $select.find('option').filter(function() {
                return $(this).val() === tagname
            })
            if (existingOption.length == 0) {
                passedTags.push(new Option(tagname, tagname, true, true))
            }
        })
        $select
            .append(passedTags)
            .val(tags)
            .trigger('change')
    }

    function addControlRow($filteringTable) {
        const availableFilters = <?= json_encode($filters) ?>;
        const $selectField = $('<select/>').addClass('fieldSelect form-select form-select-sm')
        availableFilters.forEach(filter => {
            $selectField.append($('<option/>').text(filter))
        });
        const $selectOperator = $('<select/>').addClass('fieldOperator form-select form-select-sm')
            .append([
                $('<option/>').text('=').val('='),
                $('<option/>').text('!=').val('!='),
            ])
        const $row = $('<tr/>').attr('id', 'controlRow')
            .append(
                $('<td/>').append($selectField),
                $('<td/>').append($selectOperator),
                $('<td/>').append(
                    $('<input>').attr('type', 'text').addClass('fieldValue form-control form-control-sm')
                ),
                $('<td/>').append(
                    $('<button/>').attr('type', 'button').addClass('btn btn-sm btn-primary')
                        .append($('<span/>').addClass('fa fa-plus'))
                        .click(addFiltering)
                )
            )
        $filteringTable.append($row)
    }

    function addFilteringRow($filteringTable, field, value, operator) {
        const $selectOperator = $('<select/>').addClass('fieldOperator form-select form-select-sm')
            .append([
                $('<option/>').text('=').val('='),
                $('<option/>').text('!=').val('!='),
            ]).val(operator)
        const $row = $('<tr/>')
            .append(
                $('<td/>').text(field).addClass('fieldName').data('fieldName', field),
                $('<td/>').append($selectOperator),
                $('<td/>').append(
                    $('<input>').attr('type', 'text').addClass('fieldValue form-control form-control-sm').val(value)
                ),
                $('<td/>').append(
                    $('<button/>').attr('type', 'button').addClass('btn btn-sm btn-danger')
                        .append($('<span/>').addClass('fa fa-trash'))
                        .click(removeSelf)
                )
            )
        $filteringTable.append($row)
        const $controlRow = $filteringTable.find('#controlRow')
        disableOptionFromSelect($controlRow, field)
    }

    function addFiltering() {
        const $table = $(this).closest('table.indexFilteringTable')
        const $controlRow = $table.find('#controlRow')
        const field = $controlRow.find('select.fieldSelect').val()
        const value = $controlRow.find('input.fieldValue').val()
        const operator = $controlRow.find('input.fieldOperator').val()
        addFilteringRow($table, field, value, operator)
        $controlRow.find('input.fieldValue').val('')
        $controlRow.find('select.fieldSelect').val('')
    }

    function removeSelf() {
        const $row = $(this).closest('tr')
        const $controlRow = $row.closest('table.indexFilteringTable').find('#controlRow')
        const field = $row.data('fieldName')
        $row.remove()
        enableOptionFromSelect($controlRow, field)
    }

    function disableOptionFromSelect($controlRow, optionName) {
        $controlRow.find('select.fieldSelect option').each(function() {
            const $option = $(this)
            if ($option.text() == optionName) {
                $option.prop('disabled', true)
            }
        });
    }

    function enableOptionFromSelect($controlRow, optionName) {
        $controlRow.find('select.fieldSelect option').each(function() {
            const $option = $(this)
            if ($option.text() == optionName) {
                $option.prop('disabled', false)
            }
        });
    }

    function getDataFromRow($row) {
        const rowData = {};
        rowData['name'] = $row.find('td.fieldName').data('fieldName')
        rowData['operator'] = $row.find('select.fieldOperator').val()
        rowData['value'] = $row.find('input.fieldValue').val()
        return rowData
    }

    function getRandomValue() {
        const $container = $('div[id^="table-container-"]')
        const randomValue = $container.attr('id').split('-')[2]
        return randomValue
    }
</script>