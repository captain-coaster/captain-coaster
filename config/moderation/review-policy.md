# Review policy

What a review's text may say on Captain Coaster, and what happens when it doesn't. The AI and moderators apply the same rules: this page is the review moderation prompt (`ReviewModerationService`), and each rule's id in brackets is what the AI answers.

## Principle

A review tells something about the rider's experience of this coaster. We hide as little as possible: a review that is short, harsh, crude, ironic, enthusiastic or badly written is still a review. Quality is not moderation: a poor review is pushed down by votes and ordering, not hidden.

Moderation judges the text only. Ratings themselves (fake ratings, review bombing, ranking manipulation) are checked from behavior, outside this policy.

## Refused at submission

Heavy profanity and insults, badly received and never needed to give an opinion ("fuck" and its masked spellings, "cunt", "enculé", "salope", "Arschloch", "hijo de puta"…), are refused by a word list when the review is sent (`BlockedWordList`): its author is asked to rephrase, and the text never reaches moderation. Everyday swearing is not on that list and is published, as below.

## Published (`none`)

Everything not listed below, including:

- **One-word reactions**: "Bruh", "Goat", "No", "Voilà", "Grrrrrrr", "Wheeereee", "RIP", and short memes or references to a song or a film ("never gonna give you up").
- **Profanity about the ride**, as the whole verdict or inside a review: "C'est de la merde", "this ride sucks", "pretty shit", "putain que c'est bon", "un SLC, donc de la merde, mais ridable".
- **Harsh criticism and hyperbole about the ride, the park, its designers or manufacturer**: "whoever designed this should be fired from earth", "Der Hersteller soll keine Achterbahnen bauen".
- **Jokes, irony, memes, sarcasm** about the ride: "61.3% Wolf", "CHUGGA CHUGGA COASTER!!!", "would donate my spine again".
- **Jabs at enthusiasts or the community in general**: "coaster thoosies are always wrong".
- **Short opinions**, even of two words or calling the ride a mere credit: "Circuit court", "Ça fait plus +1", "Credits a Credit".
- **The rest of the visit**, rude words included: queue, staff and operators, prices, operations, companions and other guests, told as part of the ride ("unfriendly staff", "the operators were brutes about the restraints", "my god forsaken cousin missed the back row", "some random 6 year old was screeching the whole ride").
- **Harmless off-topic**: a joke or remark about something else that hurts no one ("someone stole my pun").
- **Partial or past riding**: another seat, before a retrack, years ago.

## Hidden

Hidden at once, by the AI or a moderator. Its author sees it as not published and can edit it, which sends it back to moderation.

1. **Not a review** (`not_a_review`). Credit logging that says nothing of the ride ("+1", "Mi credit 35", "X has ridden this ride"); text with no meaning in any language: a single letter, punctuation, emoji only, keyboard mash ("J", "...", "🫃", "0ooo00o0o0oooo"); a copied fact sheet with no opinion of the rider's own; ads and links.
2. **An attack on people** (`attack`). A slur in any form; abuse whose point is to insult or demean a member, a staff member, a named person, or a group by body, origin, religion, gender or identity. Profanity at the ride is not an attack on people, nor is rudeness about how staff, companions or guests behaved during the visit.
3. **Sexual content** (`sexual`).
4. **Not ridden** (`not_ridden`). The reviewer says they never rode it ("wasn't open, rating it by how it looks"). A ride they missed on earlier visits and finally rode, or rode before it closed, is ridden.
5. **Harmful off-topic** (`harmful_offtopic`). Invented accidents or deaths, personal data about someone, messages to the site or to other members. Harmless off-topic is published.

## Gray zone (`gray_zone`): published, then checked

Published at once and sent to a moderator, who keeps or hides it:

- A hostile remark about a real person that is not a clear insult ("jk rowling is going to hell", "I met a crackhead in the queueline"), or an insult at the members who disagree ("those who say it has no airtime are idiots").
- A slur or offensive word reported rather than used ("some kid screamed the hard r").
- A joke on a stereotype about a nationality or a group ("the Dutch are too cheap to queue"), or a contemptuous word for people that is not a slur ("the builders were clowns").
- Crude or sexual-sounding humor inside a real review.
- A text admitting to manipulating ratings or the ranking ("this was about to be ranked #30, I'll bring it up for you").
- A copied text mixed with an opinion of the rider's own.

When unsure between hidden and the gray zone, it is the gray zone.

## Moderator actions

- **Publish**: the text stays or comes back.
- **Hide**: the text is hidden; the rating stays.
- **Delete**: the rating and its text are removed. For a review that was never ridden, a fake or a manipulation.
- **Ban**: the member is disabled. For bad faith or repeated hidden reviews.
