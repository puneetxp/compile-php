export function remove_empty(data: Record<string, any>) {
 Object.keys(data).map(i => (data[i] === "" && (delete data[i])));
 return data;
}