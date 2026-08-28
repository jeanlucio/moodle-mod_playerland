# 🧩 Blocks & Mini-Lessons

## Question Blocks (yellow "?")

Hitting a question block from below opens a Moodle-native `core/modal` dialog with a
multiple-choice question drawn from the activity's own internal question bank (see
[Manage Questions](#usage)). The correct option is never sent to the client until an answer is
submitted; the server validates it, records at most one distinct correct answer per question per
student, and returns immediate correct/incorrect feedback with the right option highlighted.
Answered blocks dim permanently and count toward the exit quota — they are never asked again.

## Mini-Lesson Blocks (blue "!")

A teacher can fill in up to three short, plain-text mini-lessons (max 400 characters each, no
formatting, images or video) on the activity settings form. Each non-empty lesson is shown by its
own block placed on the map. Unlike a question block, a lesson block is **never spent** — hitting
it again always re-shows the same text, so a student can revisit it as many times as needed.

## Linking a Question to a Mini-Lesson

A question can optionally be tied to one of the three mini-lessons. A question block placed right
after a lesson block draws from that lesson's linked questions first, so the practice a student
sees is actually about what they just read — not a random pick from every question in the
activity. The selection follows a four-tier fallback chain, so a block is **never** left without
a question even if the linked topic runs out:

1. An unanswered question tied to the requested topic.
2. Any question tied to the requested topic (even if already answered) — this is what keeps a
   lesson's own question showing up instead of falling through to unrelated content, once its
   only question has already been answered once.
3. Any unanswered question in the activity's general pool.
4. Any question in the activity at all.

A question left with no topic (the default) belongs to the general pool and can appear at any
question block, linked or not.

## Managing Questions

Teachers manage the activity's questions through a dedicated **Manage questions** screen (linked
from the top of the activity for anyone with the `mod/playerland:manage` capability): add, edit
or delete multiple-choice questions, and optionally choose which mini-lesson each one is linked
to. This is a per-activity internal bank, not the Moodle Question Bank — see
[Features](#features) for that as a planned integration.
