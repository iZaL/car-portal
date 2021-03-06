<?php
namespace App\Src\Car\Repository;

use App\Core\BaseRepository;
use App\Core\CrudableTrait;
use App\Src\Car\CarModel;
use Illuminate\Support\MessageBag;

class CarModelRepository extends BaseRepository
{

    use CrudableTrait;

    public $model;

    public function __construct(CarModel $model)
    {
        parent::__construct(new MessageBag);

        $this->model = $model;
    }

}