<?php
namespace App\Src\User;

use App\Core\BaseRepository;
use App\Core\CrudableTrait;
use Illuminate\Support\MessageBag;

class UserRepository extends BaseRepository
{

    use CrudableTrait;

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * Construct
     * @param User $model
     */
    public function __construct(User $model)
    {
        parent::__construct(new MessageBag);

        $this->model = $model;
    }

    public function getPasswordResetForm()
    {
        return new ResetValidator();
    }

}