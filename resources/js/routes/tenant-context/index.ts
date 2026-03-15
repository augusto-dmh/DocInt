import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
export const edit = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/settings/tenant-context',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
edit.url = (options?: RouteQueryOptions) => {
    return edit.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
edit.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
edit.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
const editForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
editForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::edit
* @see app/Http/Controllers/Settings/TenantContextController.php:15
* @route '/settings/tenant-context'
*/
editForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: edit.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

edit.form = editForm

/**
* @see \App\Http\Controllers\Settings\TenantContextController::update
* @see app/Http/Controllers/Settings/TenantContextController.php:25
* @route '/settings/tenant-context'
*/
export const update = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/settings/tenant-context',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Settings\TenantContextController::update
* @see app/Http/Controllers/Settings/TenantContextController.php:25
* @route '/settings/tenant-context'
*/
update.url = (options?: RouteQueryOptions) => {
    return update.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TenantContextController::update
* @see app/Http/Controllers/Settings/TenantContextController.php:25
* @route '/settings/tenant-context'
*/
update.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::update
* @see app/Http/Controllers/Settings/TenantContextController.php:25
* @route '/settings/tenant-context'
*/
const updateForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::update
* @see app/Http/Controllers/Settings/TenantContextController.php:25
* @route '/settings/tenant-context'
*/
updateForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\Settings\TenantContextController::destroy
* @see app/Http/Controllers/Settings/TenantContextController.php:35
* @route '/settings/tenant-context'
*/
export const destroy = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/tenant-context',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Settings\TenantContextController::destroy
* @see app/Http/Controllers/Settings/TenantContextController.php:35
* @route '/settings/tenant-context'
*/
destroy.url = (options?: RouteQueryOptions) => {
    return destroy.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Settings\TenantContextController::destroy
* @see app/Http/Controllers/Settings/TenantContextController.php:35
* @route '/settings/tenant-context'
*/
destroy.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::destroy
* @see app/Http/Controllers/Settings/TenantContextController.php:35
* @route '/settings/tenant-context'
*/
const destroyForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Settings\TenantContextController::destroy
* @see app/Http/Controllers/Settings/TenantContextController.php:35
* @route '/settings/tenant-context'
*/
destroyForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const tenantContext = {
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default tenantContext