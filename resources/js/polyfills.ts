// TODO: Remove when minimum browser target supports Map.prototype.getOrInsertComputed
// (Chrome 131+, or when pdfjs-dist ships its own polyfill)
if (!('getOrInsertComputed' in Map.prototype)) {
    // @ts-expect-error polyfill for TC39 Stage 3 proposal
    Map.prototype.getOrInsertComputed = function <K, V>(
        key: K,
        callback: (key: K) => V,
    ): V {
        if (!this.has(key)) {
            this.set(key, callback(key));
        }
        return this.get(key) as V;
    };
}
