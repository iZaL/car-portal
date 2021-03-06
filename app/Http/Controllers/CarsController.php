<?php
namespace App\Http\Controllers;

use App\Events\CarWasPosted;
use App\Src\Car\CarRepository;
use App\Src\Car\Repository\CarBrandRepository;
use App\Src\Car\Repository\CarMakeRepository;
use App\Src\Car\Repository\CarModelRepository;
use App\Src\Car\Repository\CarTypeRepository;
use App\Src\Favorite\FavoriteRepository;
use App\Src\Photo\PhotoRepository;
use App\Src\Tag\TagRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;

class CarsController extends Controller
{

    private $carRepository;

    /**
     * @param CarRepository $carRepository
     */
    public function __construct(CarRepository $carRepository)
    {
        $this->carRepository = $carRepository;
        Auth::loginUsingId(1);
    }

    public function index()
    {
        return view('module.cars.index');
    }

    public function show($id)
    {
        $car = $this->carRepository->model->with([
            'model.brand',
            'user',
            'thumbnail',
            'photos',
            'favorited'
        ])->find($id);

        return view('module.cars.view', compact('car'));
    }

    /**
     * @param CarModelRepository $carModelRepository
     * @param TagRepository $tagRepository
     * @return \Illuminate\View\View
     */
    public function create(CarModelRepository $carModelRepository, TagRepository $tagRepository)
    {

        $models = ['' => ''] + $carModelRepository->model->get()->lists('name_en', 'id');
        $tags = $tagRepository->model->get()->lists('name', 'id');

        return view('module.cars.create', compact('models', 'tags'));
    }

    /**
     * @param PhotoRepository $photoRepository
     * @param TagRepository $tagRepository
     * @param Request $request
     * @return mixed
     * @throws \Exception
     * @internal param PostCarRequest $request
     */
    public function store(PhotoRepository $photoRepository, TagRepository $tagRepository, Request $request)
    {
        $val = $this->carRepository->getCreateForm();
        $user = Auth::user(); // todo: replace with Auth::user();

        if (!$val->isValid()) {

            return Redirect::back()->with('errors', $val->getErrors())->withInput();
        }

        $car = $this->carRepository->create(array_merge(['user_id' => $user->id], $val->getInputData()));

        if ($car) {
            // upload the file to the server

            $upload = $photoRepository->attach(Input::file('thumbnail'), $car, ['thumbnail' => 1]);

            if (!$upload) {

                $car->delete();

                return Redirect::back()->with('errors', ['Could Not upload the photo, try again'])->withInput();
            }

            // save the file in the db
            $tags = is_array(Input::get('tags')) ? Input::get('tags') : [];
            if (!(empty($tags))) {
                $tagRepository->attach($car, $tags);
            }

            // fire notify user filter event
            Event::fire(new CarWasPosted($car, Auth::user(), $request, $this->carRepository));

        }

        return Redirect::action('CarsController@edit', [$car->id, '#optionals'])->with('success', 'Saved');
    }

    public function edit($id, CarModelRepository $carModelRepository, TagRepository $tagRepository)
    {
        $car = $this->carRepository->model->find($id);
        $tags = $tagRepository->model->get()->lists('name_en', 'id');
        $models = $carModelRepository->model->get()->lists('name_en', 'id');
        $attachedTags = ['' => ''] + $car->tags->lists('name_en', 'id');

        return view('module.cars.edit', compact('car', 'tags', 'attachedTags', 'models'));
    }

    public function update($id, PhotoRepository $photoRepository, TagRepository $tagRepository)
    {
        $val = $this->carRepository->getEditForm($id);
        $userId = Auth::user()->id; // todo: replace with Auth::user()->id;

        if (!$val->isValid()) {

            return Redirect::back()->with('errors', $val->getErrors())->withInput();
        }

        $car = $this->carRepository->update($id, array_merge(['user_id' => $userId], $val->getInputData()));

        if ($car) {

            if (Input::hasFile('thumbnail')) {
                // upload the file to the server
                $upload = $photoRepository->replace(Input::file('thumbnail'), $car, ['thumbnail' => 1], $id);

                if (!$upload) {

                    $car->delete();

                    return Redirect::back()->with('errors', ['Could Not upload photo, try again'])->withInput();
                }
            }

            if (Input::hasFile('photos') && is_array(Input::file('photos'))) {
                foreach (Input::file('photos') as $photo) {
                    $photoRepository->attach($photo, $car);
                }
            }

            // save the file in the db
            $tags = is_array(Input::get('tags')) ? Input::get('tags') : [];

            if (!(empty($tags))) {
                $tagRepository->attach($car, $tags);
            }
        }

        return Redirect::action('CarsController@show', $car->id)->with('success', 'Saved');

    }

    /*********************************************************************************************************
     * Api Routes
     ********************************************************************************************************/
    /**
     * Gets Car Asynchronously For Search Filters
     * @param CarRepository $carRepository
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function getCars(CarRepository $carRepository, Request $request)
    {
        $getMakes = array_filter(explode(',', $request->get('make')));
        $getBrands = array_filter(explode(',', $request->get('brand')));
        $getModels = array_filter(explode(',', $request->get('model')));
        $getTypes = array_filter(explode(',', $request->get('type')));

        // If Mileage From is set and less then Max Mileage Set The Value, else SET default value from repository class
        $mileageFrom = ($request->get('mileage_from') && $request->get('mileage_from') < $carRepository::MAXMILEAGE) ? $request->get('mileage_from') : $carRepository::MILEAGEDEFAULTFROM;

        // If Mileage To is Set and Mileage To is Greater Than Mileage From set the Value, Else SET Default Mileage To from Repository Class
        $mileageTo = ($request->get('mileage_to') && $request->get('mileage_to') > $mileageFrom) ? $request->get('mileage_to') : $carRepository::MILEAGEDEFAULTTO;

        $priceFrom = ($request->get('price_from') && $request->get('price_from') < $carRepository::MAXPRICE) ? $request->get('price_from') : $carRepository::PRICEDEFAULTFROM;

        $priceTo = ($request->get('price_to') && $request->get('price_to') > $priceFrom) ? $request->get('price_to') : $carRepository::PRICEDEFAULTTO;

        $yearFrom = ($request->get('year_from') && $request->get('year_from') < $carRepository::MAXYEAR) ? $request->get('year_from') : $carRepository::YEARDEFAULTFROM;

        $yearTo = ($request->get('year_to') && $request->get('year_to') > $yearFrom) ? $request->get('year_to') : $carRepository::YEARDEFAULTTO;

        $maxPrice = $carRepository::MAXPRICE;
        $maxYear = $carRepository::MAXYEAR;
        $maxMileage = $carRepository::MAXMILEAGE;

        $paginate = 100;
        $relatedModels = ['thumbnail'];

        // prepare for caching. make a new collection of search filters
//        $cacheName = $carRepository->getCacheName($getMakes, $getBrands, $getTypes, $getModels, $priceFrom,
//            $priceTo, $mileageFrom, $mileageTo, $yearFrom, $yearTo);

//        if (!$cache->has($cacheName)) {

        $cars = $carRepository->model->with($relatedModels);

        if ($getModels) {
            $cars->whereIn('model_id', $getModels);
        } elseif ($getBrands) {
            $cars->join('car_models', 'cars.model_id', '=', 'car_models.id')
                ->join('car_brands', 'car_brands.id', '=', 'car_models.brand_id')
                ->whereIn('car_brands.id', $getBrands);
            if ($getTypes) {
                $cars->whereIn('car_models.type_id', $getTypes);
            }

        } elseif ($getMakes) {
            $cars->join('car_models', 'cars.model_id', '=', 'car_models.id')
                ->join('car_brands', 'car_models.brand_id', '=', 'car_brands.id')
                ->join('car_makes', 'car_brands.make_id', '=', 'car_makes.id')
                ->whereIn('car_makes.id', $getMakes);
            if ($getTypes) {
                $cars->whereIn('car_models.type_id', $getTypes);
            }
        }

        $mileageTo < $maxMileage ?
            $cars->whereBetween('mileage', [$mileageFrom, $mileageTo]) :
            $cars->whereBetween('mileage', [$mileageFrom, $maxMileage]);

        $priceTo < $maxPrice ?
            $cars->whereBetween('price', [$priceFrom, $priceTo]) :
            $cars->whereBetween('price', [$priceFrom, $maxPrice]);

        $yearTo < $maxYear ?
            $cars->whereBetween('year', [$yearFrom, $yearTo]) :
            $cars->whereBetween('year', [$yearFrom, $maxYear]);

        $cars->select(['cars.id', 'model_id', 'year', 'mileage', 'price', 'created_at'])
            ->orderBy('cars.created_at', 'DESC');

//            $cache->rememberForever($cacheName, function () use ($cars) {
        return $cars->paginate($paginate);
//            });
//        }

//        return $cache->get($cacheName);

    }


    public function filter(
        CarMakeRepository $carMakeRepository,
        CarBrandRepository $carBrandRepository,
        CarTypeRepository $carTypeRepository,
        CarModelRepository $carModelRepository
    ) {
        // get the inputs and make it an array
        $getMakes = array_filter(explode(',', Input::get('make')));
        $getBrands = array_filter(explode(',', Input::get('brand')));
        $getModels = array_filter(explode(',', Input::get('model')));
        $getTypes = array_filter(explode(',', Input::get('type')));

        // Get the Car Filter Initial Values From the Database
        $makes = $carMakeRepository->getNames();
        $brands = $carBrandRepository->getNames();
        $models = $carModelRepository->getNames();
        $types = $carTypeRepository->getNames();

        if ($getMakes) {
            // find the brands
            $brands = $carBrandRepository->model
                ->whereIn('make_id', $getMakes)
                ->get(['id', 'name_en as name']);

            // find the models
            if (!$getBrands) {
                // If Type is in the GET Request
                if ($getTypes) {

                    // Fetch the Models For Make ID
                    $models = $carModelRepository->model
                        ->whereHas('brand', function ($query) use ($getMakes, $getTypes) {
                            $query->whereIn('car_brands.make_id', $getMakes)->whereIn('type_id', $getTypes);
                        })->get(['id', 'name_en as name']);

                } else {

                    // Just Get the Models For Make ID
                    $models = $carModelRepository->model
                        ->whereHas('brand', function ($query) use ($getMakes) {
                            $query->whereIn('car_brands.make_id', $getMakes);
                        })->get(['id', 'name_en as name']);
                }
            }

            // 1- First Get All the ID's
            $makesArray = $makes->modelKeys();

            // Find the ID's that are not in the GET Request
            $makesNotInGET = array_diff($makesArray, $getMakes);

            if (count($makesNotInGET)) {
                $makes = $carMakeRepository->model->whereIn('id', $makesNotInGET)->get(['id', 'name_en as name']);
            } else {
                $makes = [];
            }
        }

        if ($getBrands) {

            // If Type is in GET Request
            if ($getTypes) {

                // Fetch the Models for type Where Has the Brand ID and Type Id
                $models = $carModelRepository->model->whereIn('brand_id', $getBrands)
                    ->whereIn('type_id', $getTypes)
                    ->get(['id', 'name_en as name']);

            } else {

                // Fetch the Models for type Where Has the Brand ID only
                $models = $carModelRepository->model
                    ->whereIn('brand_id', $getBrands)
                    ->get(['id', 'name_en as name']);
            }

            $brandsArray = $brands->modelKeys(); // Get the ID

            // Remove Unnecessary Brands From Selected Elements
            foreach ($getBrands as $key => $value) {
                if (!in_array($value, $brandsArray)) {
                    unset($getBrands[$key]);
                }
            }

            // pass the array only that's value is not the in GET REQUEST
            $brandsNotInGET = array_diff($brandsArray, $getBrands);

            if (count($brandsNotInGET)) {

                // If there are any brands left in the DB that are not in the GET Request, Then fetch it.
                $brands = $carBrandRepository->model->whereIn('id', $brandsNotInGET)->get(['id', 'name_en as name']);
            } else {
                // pass an empty string, so that no option will be showed to select in the front end select2 element
                $brands = [];
            }

        }

        if ($getTypes) {

            if (!$getMakes && !$getBrands) {

                $models = $carModelRepository->model
                    ->whereIn('type_id', $getTypes)
                    ->get(['id', 'name_en as name']);

            }
            // Remove unncessary type values from the GET array
            $typesArray = $types->modelKeys();

            foreach ($getTypes as $key => $value) {

                if (!in_array($value, $typesArray)) {
                    unset($typesArray[$key]);
                }
            }

            $typesNotInGET = array_diff($typesArray, $getTypes);

            if (count($typesNotInGET)) {

                $types = $carTypeRepository->model->whereIn('id', $typesNotInGET)->get(['id', 'name_en as name']);
            } else {

                $types = [];
            }

        }

        if ($getModels) {

            $modelsArray = $models->modelKeys();

            foreach ($getModels as $key => $value) {
                if (!in_array($value, $modelsArray)) {
                    unset($getModels[$key]);
                }
            }
            $modelsNotInGET = array_diff($modelsArray, $getModels);

            if (count($modelsNotInGET)) {

                $models = $carModelRepository->model->whereIn('id', $modelsNotInGET)->get(['id', 'name_en as name']);
            } else {
                $models = [];
            }

        }

        // Cast the Array values into Int, so that it matches with the selected value and avoid duplicate queries
        array_walk($getBrands, function (&$item) {
            $item = (int)$item;
        });
        array_walk($getModels, function (&$item) {
            $item = (int)$item;
        });

        $return = [
            'results' => [
                'makes'       => $makes,
                'brands'      => $brands,
                'types'       => $types,
                'models'      => $models,
                'brandsArray' => array_values(array_unique($getBrands)),
                'modelsArray' => array_values(array_unique($getModels))
            ]
        ];

        return $return;
    }

    public function favorite($id, FavoriteRepository $favoriteRepository)
    {
        $car = $this->carRepository->findById($id);

        if ($favoriteRepository->attach($car)) {
            return Response::make(array('class' => 'success', 'message' => ['favorited']), 200);
        }
    }

    /**
     * @param CarMakeRepository $carMakeRepository
     * @param CarBrandRepository $carBrandRepository
     * @param CarTypeRepository $carTypeRepository
     * @param CarModelRepository $carModelRepository
     * @return array
     */
    public function getFilterNames(
        CarMakeRepository $carMakeRepository,
        CarBrandRepository $carBrandRepository,
        CarTypeRepository $carTypeRepository,
        CarModelRepository $carModelRepository
    ) {
        // get the inputs and make it an array
        $getMakes = array_filter(explode(',', Input::get('make')));
        $getBrands = array_filter(explode(',', Input::get('brand')));
        $getModels = array_filter(explode(',', Input::get('model')));
        $getTypes = array_filter(explode(',', Input::get('type')));

        $makes = empty($getMakes) ? '' : $carMakeRepository->getNames($getMakes);
        $brands = empty($getBrands) ? '' : $carBrandRepository->getNames($getBrands);
        $models = empty($getModels) ? '' : $carModelRepository->getNames($getModels);
        $types = empty($getTypes) ? '' : $carTypeRepository->getNames($getTypes);

        return $return = [
            'results' => [
                'makes'  => $makes,
                'brands' => $brands,
                'types'  => $types,
                'models' => $models
            ]
        ];

    }

}