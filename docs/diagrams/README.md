# Diagrams

Mermaid source files for BAMBOSA's database and data-flow diagrams. These
are plain text, not images — that's intentional, so they can be updated
alongside the schema/code instead of getting silently out of date the way
a hand-drawn image would.

- `erd.mermaid` — Entity-Relationship Diagram, generated directly from
  `sql/bambosa_db.sql` (every table, column, PK/FK/unique key).
- `dfd_level0.mermaid` — Level 0 (Context) Data Flow Diagram: the whole
  system as one process, with its external entities (Member, Officer,
  SMTP server, OpenStreetMap tiles).
- `dfd_level1.mermaid` — Level 1 DFD: the system decomposed into its 7
  major processes (auth, farm lots, sectors, machinery, scheduling,
  billing, dashboard/calendar), each mapped to its real backing table(s).

## How to view or edit these

1. Paste the contents of any `.mermaid` file into
   [mermaid.live](https://mermaid.live) for an instant, editable preview.
   Use its Export button to save as PNG/SVG for a Word doc, slide deck, or
   printed paper.
2. Or drop the contents into a fenced code block in any Markdown file
   that supports Mermaid rendering (GitHub, GitLab, Notion, Obsidian,
   VS Code with the Mermaid extension, etc.):

   ```` `​``mermaid
   erDiagram
       ...
   `​`` ````

## Keeping these current

If the schema changes (a new table, a new column, a new FK), update
`erd.mermaid` to match `sql/bambosa_db.sql` — it's a straightforward
mechanical translation: one `CREATE TABLE` block becomes one entity block,
one `FOREIGN KEY` line becomes one relationship line. The DFDs only need
updating if an entirely new *feature area* is added (a new process bubble)
or an existing one starts touching a different data store — small
UI/behavior tweaks within an existing feature don't need a DFD change.
