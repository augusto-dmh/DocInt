import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
export const queueHealth = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: queueHealth.url(options),
    method: 'get',
})

queueHealth.definition = {
    methods: ["get","head"],
    url: '/admin/queue-health',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
queueHealth.url = (options?: RouteQueryOptions) => {
    return queueHealth.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
queueHealth.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: queueHealth.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
queueHealth.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: queueHealth.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
const queueHealthForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: queueHealth.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
queueHealthForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: queueHealth.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Admin\QueueHealthController::__invoke
* @see app/Http/Controllers/Admin/QueueHealthController.php:18
* @route '/admin/queue-health'
*/
queueHealthForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: queueHealth.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

queueHealth.form = queueHealthForm

const admin = {
    queueHealth: Object.assign(queueHealth, queueHealth),
}

export default admin