<?php

namespace App\Model\Table;

use App\Model\Table\AppTable;
use Cake\Validation\Validator;

class TagsTable extends AppTable
{
    protected $_accessible = [
        'id' => false
    ];

    public function initialize(array $config): void
    {
        // $this->setTable('tags');
        $this->setTable('tags_tags');
        $this->setDisplayField('label'); // Change to name?
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->notBlank('label');
        return $validator;
    }
}
