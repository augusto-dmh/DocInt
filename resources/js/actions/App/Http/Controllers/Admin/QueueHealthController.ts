import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
const QueueHealthController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: QueueHealthController.url(options),
    method: 'get',
})

QueueHealthController.definition = {
    methods: ["get","head"],
    url: '/admin/queue-health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
QueueHealthController.url = (options?: RouteQueryOptions) => {
    return QueueHealthController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
QueueHealthController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: QueueHealthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
QueueHealthController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: QueueHealthController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
const QueueHealthControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: QueueHealthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
QueueHealthControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: QueueHealthController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
QueueHealthControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: QueueHealthController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

QueueHealthController.form = QueueHealthControllerForm

export default QueueHealthController