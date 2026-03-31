import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
export const index = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/documents/{document}/comments',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
index.url = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { document: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { document: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            document: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        document: typeof args.document === 'object'
        ? args.document.id
        : args.document,
    }

    return index.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
index.get = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
index.head = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
const indexForm = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
indexForm.get = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::index
* @see app/Http/Controllers/DocumentCommentController.php:18
* @route '/documents/{document}/comments'
*/
indexForm.head = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\DocumentCommentController::store
* @see app/Http/Controllers/DocumentCommentController.php:33
* @route '/documents/{document}/comments'
*/
export const store = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/documents/{document}/comments',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\DocumentCommentController::store
* @see app/Http/Controllers/DocumentCommentController.php:33
* @route '/documents/{document}/comments'
*/
store.url = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { document: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { document: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            document: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        document: typeof args.document === 'object'
        ? args.document.id
        : args.document,
    }

    return store.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentCommentController::store
* @see app/Http/Controllers/DocumentCommentController.php:33
* @route '/documents/{document}/comments'
*/
store.post = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::store
* @see app/Http/Controllers/DocumentCommentController.php:33
* @route '/documents/{document}/comments'
*/
const storeForm = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::store
* @see app/Http/Controllers/DocumentCommentController.php:33
* @route '/documents/{document}/comments'
*/
storeForm.post = (args: { document: string | number | { id: string | number } } | [document: string | number | { id: string | number } ] | string | number | { id: string | number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\DocumentCommentController::update
* @see app/Http/Controllers/DocumentCommentController.php:73
* @route '/documents/{document}/comments/{comment}'
*/
export const update = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/documents/{document}/comments/{comment}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\DocumentCommentController::update
* @see app/Http/Controllers/DocumentCommentController.php:73
* @route '/documents/{document}/comments/{comment}'
*/
update.url = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            document: args[0],
            comment: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        document: typeof args.document === 'object'
        ? args.document.id
        : args.document,
        comment: typeof args.comment === 'object'
        ? args.comment.id
        : args.comment,
    }

    return update.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace('{comment}', parsedArgs.comment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentCommentController::update
* @see app/Http/Controllers/DocumentCommentController.php:73
* @route '/documents/{document}/comments/{comment}'
*/
update.patch = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::update
* @see app/Http/Controllers/DocumentCommentController.php:73
* @route '/documents/{document}/comments/{comment}'
*/
const updateForm = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::update
* @see app/Http/Controllers/DocumentCommentController.php:73
* @route '/documents/{document}/comments/{comment}'
*/
updateForm.patch = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\DocumentCommentController::destroy
* @see app/Http/Controllers/DocumentCommentController.php:113
* @route '/documents/{document}/comments/{comment}'
*/
export const destroy = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/documents/{document}/comments/{comment}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\DocumentCommentController::destroy
* @see app/Http/Controllers/DocumentCommentController.php:113
* @route '/documents/{document}/comments/{comment}'
*/
destroy.url = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            document: args[0],
            comment: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        document: typeof args.document === 'object'
        ? args.document.id
        : args.document,
        comment: typeof args.comment === 'object'
        ? args.comment.id
        : args.comment,
    }

    return destroy.definition.url
            .replace('{document}', parsedArgs.document.toString())
            .replace('{comment}', parsedArgs.comment.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DocumentCommentController::destroy
* @see app/Http/Controllers/DocumentCommentController.php:113
* @route '/documents/{document}/comments/{comment}'
*/
destroy.delete = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::destroy
* @see app/Http/Controllers/DocumentCommentController.php:113
* @route '/documents/{document}/comments/{comment}'
*/
const destroyForm = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DocumentCommentController::destroy
* @see app/Http/Controllers/DocumentCommentController.php:113
* @route '/documents/{document}/comments/{comment}'
*/
destroyForm.delete = (args: { document: string | number | { id: string | number }, comment: string | number | { id: string | number } } | [document: string | number | { id: string | number }, comment: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const DocumentCommentController = { index, store, update, destroy }

export default DocumentCommentController