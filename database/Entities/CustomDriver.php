<?php

namespace Flute\Modules\GiveCore\database\Entities;

use Cycle\ActiveRecord\ActiveRecord;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;

#[Entity(table: 'givecore_custom_drivers')]
#[Index(columns: ['alias'], unique: true)]
class CustomDriver extends ActiveRecord
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'string')]
    public string $alias;

    #[Column(type: 'string')]
    public string $name;

    #[Column(type: 'string', nullable: true)]
    public ?string $description = null;

    #[Column(type: 'string', default: 'ph.bold.database-bold')]
    public string $icon = 'ph.bold.database-bold';

    #[Column(type: 'string', default: 'other')]
    public string $category = 'other';

    #[Column(type: 'string', nullable: true)]
    public ?string $mod_key = null;

    #[Column(type: 'string', default: 'steam')]
    public string $identifier = 'steam';

    #[Column(type: 'text')]
    public string $sql_deliver = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $sql_check = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $fields = null;

    public function getFields(): array
    {
        return $this->fields ? json_decode($this->fields, true) : [];
    }

    public function setFields(array $fields): void
    {
        $this->fields = json_encode($fields, JSON_UNESCAPED_UNICODE);
    }

    public function toConfig(): array
    {
        return [
            'alias' => $this->alias,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'icon' => $this->icon,
            'category' => $this->category,
            'mod_key' => $this->mod_key ?? '',
            'identifier' => $this->identifier,
            'sql_deliver' => $this->sql_deliver,
            'sql_check' => $this->sql_check ?? '',
            'fields' => $this->getFields(),
        ];
    }
}
