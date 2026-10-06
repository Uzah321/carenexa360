/**
 * Suggested values for a text input — pair with `<Input list={id} />`.
 * The input still accepts anything typed; these are just quick picks
 * (System Settings → Reference Data supplies most of them).
 */
export function Suggestions({ id, items }: { id: string; items: readonly string[] }) {
  return (
    <datalist id={id}>
      {items.map((item) => (
        <option key={item} value={item} />
      ))}
    </datalist>
  );
}
