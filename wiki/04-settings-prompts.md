# 4. Prompts

**Where to find it:** Contact → Settings → **Prompts** tab.

"Prompts" are the written instructions given to the AI. The defaults work well out of the box, so most people never need to touch this tab — but if you want the AI's tone or focus to better match your business, this is where to adjust it.

## Analysis Prompt

This is the instruction the AI follows when it reads a new submission and decides on a summary, category, and priority.

You can insert any of these placeholders anywhere in your prompt text, and the plugin will fill in the real value for each submission automatically:

* `{message}` — the submitted message text.
* `{customer_name}` — the sender's name.
* `{form_name}` — which form was used.
* `{submitted_fields}` — every other field the visitor filled in.
* `{categories}` — the list of categories the AI can choose from.

Edit the text in the **Prompt template** box to change how analysis works. Changes only affect *new* submissions from that point on — anything already analyzed keeps its original summary and category.

## Reply Draft Prompt

This is the instruction the AI follows when writing a suggested reply. It supports its own set of placeholders:

* `{message}` — the original submission.
* `{summary}` — the AI's own summary of it.
* `{tone}` — the tone you've selected below.
* `{signature}` — your sign-off.

## Reply Tone

Choose a default tone for AI-drafted replies from the dropdown: **Friendly and professional**, **Formal**, **Casual**, or **Concise**. This is used automatically every time the AI drafts a reply, though you can always edit the wording yourself before sending.

## Saving or resetting

* Click **Save Prompts** to keep your changes.
* Click **Reset to Defaults** if you want to discard your edits and go back to the plugin's original wording — useful if you've experimented and want a clean starting point again.
