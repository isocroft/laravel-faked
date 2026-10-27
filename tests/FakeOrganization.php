<?php

namespace LaravelFaked\Tests;

use LaravelFaked\DataSource\FakeModel;

class FakeOrganization extends FakeModel
{
    protected ?string $table = 'tbl_tenants';
    protected array $fillable = ['name'];
    protected array $hidden = ['slug_id', 'reg_number'];
    protected array $casts = [];
    protected array $attributes = [];

}

?>
