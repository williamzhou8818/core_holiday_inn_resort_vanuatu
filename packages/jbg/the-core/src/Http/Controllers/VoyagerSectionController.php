<?php

namespace TCG\Voyager\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Events\BreadDataAdded;
use TCG\Voyager\Events\BreadDataDeleted;
use TCG\Voyager\Events\BreadDataRestored;
use TCG\Voyager\Events\BreadDataUpdated;
use TCG\Voyager\Events\BreadImagesDeleted;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Http\Controllers\Traits\BreadRelationshipParser;

class VoyagerSectionController extends VoyagerBaseController
{
    use BreadRelationshipParser;

    public function show(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        $isSoftDeleted = false;

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);

            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
                $model = $model->withTrashed();
            }
            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
                $model = $model->{$dataType->scope}();
            }
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
            if ($dataTypeContent->deleted_at) {
                $isSoftDeleted = true;
            }
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        // Replace relationships' keys for labels and create READ links if a slug is provided.
        $dataTypeContent = $this->resolveRelations($dataTypeContent, $dataType, true);

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'read');

        // Check permission
        $this->authorize('read', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);

        // SUB SECTION LOGIC
        // get table references
        $tableReference = $dataTypeContent->table_reference;
        // get records from table for display_status == 1
        $subSectionDataTypeContent = DB::table($tableReference)->where('display_status', '1')->orderBy('position','asc')->get();

        $view = 'voyager::bread.read';

        if (view()->exists("voyager::$slug.read")) {
            $view = "voyager::$slug.read";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable', 'isSoftDeleted', 'subSectionDataTypeContent'));
    }

    public function edit(Request $request, $id)
    {
        $slug = $this->getSlug($request);

        $dataType = Voyager::model('DataType')->where('slug', '=', $slug)->first();

        if (strlen($dataType->model_name) != 0) {
            $model = app($dataType->model_name);

            // Use withTrashed() if model uses SoftDeletes and if toggle is selected
            if ($model && in_array(SoftDeletes::class, class_uses_recursive($model))) {
                $model = $model->withTrashed();
            }
            if ($dataType->scope && $dataType->scope != '' && method_exists($model, 'scope'.ucfirst($dataType->scope))) {
                $model = $model->{$dataType->scope}();
            }
            $dataTypeContent = call_user_func([$model, 'findOrFail'], $id);
        } else {
            // If Model doest exist, get data from table name
            $dataTypeContent = DB::table($dataType->name)->where('id', $id)->first();
        }

        foreach ($dataType->editRows as $key => $row) {
            $dataType->editRows[$key]['col_width'] = isset($row->details->width) ? $row->details->width : 100;
        }

        // If a column has a relationship associated with it, we do not want to show that field
        $this->removeRelationshipField($dataType, 'edit');

        // Check permission
        $this->authorize('edit', $dataTypeContent);

        // Check if BREAD is Translatable
        $isModelTranslatable = is_bread_translatable($dataTypeContent);



        /*
        // get field options
        $fieldOptions = SchemaManager::describeTable((strlen($dataType->model_name) != 0)
                ? DB::getTablePrefix().app($dataType->model_name)->getTable()
                : DB::getTablePrefix().$dataType->name
        );



        // get the details in subsection edit
        $subSectionName = 'sub_sections';
        $editRows = $dataType->editRows;
        $subSectionEdit = null;
        foreach($editRows as $editRow) {
            if ($editRow->field == $subSectionName) {
                $subSectionEdit = $editRow;
                break;
            }
        }
        if (!isset($subSectionEdit))
            throw new Exception($subSectionName . " is not defined in table.");
        // get the details settings
        $subSectionDetails = $subSectionEdit->details;

        var_dump($subSectionEdit->details);
        // Get subsections from other models
*/
        /*
        foreach (Voyager::model('DataType')::all() as $subSectionDataType) {
            // skip current model
            if ($subSectionDataType->slug == $dataType->slug)
                continue;
            // get fields
            $subSectionfieldOptions = SchemaManager::describeTable((strlen($subSectionDataType->model_name) != 0)
                    ? DB::getTablePrefix().app($subSectionDataType->model_name)->getTable()
                    : DB::getTablePrefix().$subSectionDataType->name
            );
            // check if section_type field exists
            $sectionTypeField = $subSectionfieldOptions->get('section_type');
            if (!isset($sectionTypeField))
                continue;
            // get records that match current section type
            $currentType = $dataTypeContent->type;
            $subSectionDataTypeContent = DB::table($subSectionDataType->name)->where('section_type', $currentType)->get();
            var_dump($subSectionDataTypeContent);
            var_dump($subSectionDataTypeContent);
            var_dump($subSectionDataTypeContent);
            var_dump($subSectionDataTypeContent);
            var_dump($currentType);
        }
        */

        $view = 'voyager::bread.edit-add';

        if (view()->exists("voyager::$slug.edit-add")) {
            $view = "voyager::$slug.edit-add";
        }

        return Voyager::view($view, compact('dataType', 'dataTypeContent', 'isModelTranslatable'));
    }
}
