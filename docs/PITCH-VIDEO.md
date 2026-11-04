# JoBauko pitch video

Two parts:

1. **The prompt**: paste it into a new Claude Code session to have Claude build the video with Remotion.
2. **The script**: what you say while the video plays. The video's scenes are timed to it.

---

## 1. Prompt for Claude

Copy everything inside the box.

````text
Create a 60-second motion graphics video that introduces JoBauko, a website I built. Use the Remotion skills I have installed: read the Remotion best-practices and create skills before writing any code, and follow them.

The video plays behind me while I pitch the website live. I speak over it, so:
- No voice-over. No music unless I ask for it later; leave an empty audio slot.
- On-screen text is short (headlines and labels, never paragraphs) so the audience can listen to me, not read.
- Each scene must start exactly at the time given below. My script is timed to these scenes.

## What JoBauko is

JoBauko is the opportunity portal of the Municipality of Bauko, Mountain Province, Philippines. It brings four kinds of opportunities into one website for residents of Bauko's 22 barangays:
- Jobs
- Scholarships
- Livelihood programs
- Seminars and trainings

It is built by Montanosa Tech for the Municipality of Bauko.

Live site: https://jobauko.onrender.com
The site runs on a free host that sleeps when idle. The first page load can take up to a minute. Wait for it and reload; it is not broken.

## Visit the site first

Before designing anything, open the live site and take screenshots of these public pages at 1920x1080:
- `/`: the landing page, including the hero, the "How it works" steps and the benefits.
- `/opportunities`: the listing with its filters.
- One opportunity page of each kind, chosen from the list:
  - a job, such as "Front Desk Associate"
  - a scholarship, such as "Highlands College Scholarship Grant"
  - a livelihood program, such as "Swine Dispersal Program"
  - a training, such as "Housekeeping Skills Training"
- `/register` and `/organizations/register`
- `/privacy`

Use the screenshots for the look of the video: colors, fonts, the card style, and the red-and-black striped band at the top of the header (a Cordillera textile pattern).

Do not log in, create accounts or submit any form on the live site. It is a real site with real data. Show the signed-in screens (seeker profile, match score, application tracking, organization and admin dashboards) as clean, simplified motion-graphic recreations in the site's style. Use only the facts in this prompt for them.

## Brand

- Primary green: #007a35. Hero gradient: #006b31 → #00592a → #0d2418. Bright green in dark areas: #3ecf7a. Text on green: white.
- Font: Instrument Sans (weights 400, 500, 600), from fonts.bunny.net or Google Fonts.
- Logos are both shown as circles with a white background:
  - The seal of the Municipality of Bauko: https://jobauko.onrender.com/images/bauko-seal.png
  - The Montanosa Tech logo: https://jobauko.onrender.com/images/montanosa-tech.png
  Download them into the Remotion project's public/ folder. Never redraw, recolor or crop either logo, especially the seal.
- Tagline from the site: "Discover Opportunities. Build Your Future."

## Format

- 1920x1080, 30 fps, exactly 60 seconds (1800 frames).
- One scene per section below, with smooth transitions that end before the next scene's start time.
- Render to out/jobauko-pitch.mp4 (H.264). Also export a still of the final frame as out/jobauko-pitch-end.png.

## Scenes (start time → content)

**Scene 1 · 0:00–0:05 · Opening**
Both circular logos side by side, then "JoBauko" and the tagline "Discover Opportunities. Build Your Future." Small label: "Municipality of Bauko, Mountain Province".

**Scene 2 · 0:05–0:11 · The problem**
Four scattered cards drifting apart: Jobs · Scholarships · Livelihood programs · Seminars and trainings. Words: "Opportunities are scattered." Then a map-like row of dots that light up: "22 barangays".

**Scene 3 · 0:11–0:18 · One portal**
The cards snap together into one website frame (use the landing page screenshot). Show the search bar and the listing with filters (use the /opportunities screenshot). Words: "One place for every opportunity."

**Scene 4 · 0:18–0:26 · Seeker profile and resume**
- Sign-up card with the real field labels "Barangay" and "Current status" (Student, Fresh graduate, Unemployed, Self-employed and others).
- A resume file drops in. Fields fill in one by one: Education, Skills. A "Confirm" button is pressed, because the seeker reviews what was read before anything is saved.
- A second beat: "No resume? Build one." A clean one-page resume PDF slides out.
Words: "Upload your resume, or build one."

**Scene 5 · 0:26–0:34 · Matching you can understand**
An opportunity card with a match score ring filling to 82%, and the site's real badge text "82% · Strong match". Beside it, the reasons with check marks, worded as the site words them:
- "You have 3 of 4 listed skills"
- "Your education meets the requirement"
- "In your barangay, Poblacion"
Words: "Recommendations with the reasons why." Do not use the words AI, artificial intelligence or machine learning anywhere. The matching is rule-based and explainable.

**Scene 6 · 0:34–0:41 · Apply and stay updated**
A progress tracker moving through the real application statuses, in order: Submitted → Under review → Shortlisted → For interview → Approved.
Notification chips pop in: "SMS", "Email", "In-app". A small calendar chip: "Deadline reminder".
Words: "Apply online. Track every step."

**Scene 7 · 0:41–0:48 · For organizations**
- A "Verified" badge stamps onto an organization card.
- A "Post an opportunity" form shows the four category tabs.
- An applicant list with status dropdowns.
- Three team role chips: Admin · Recruitment officer · Reviewer.
Words: "Verified organizations. Simple hiring."

**Scene 8 · 0:48–0:55 · For the municipality**
A dashboard with:
- a bar chart labelled "Applications by barangay" (decorative bars; no real numbers)
- an "Export PDF / Excel" button
- tiles for "Organization verification", "Listing reports", "Announcements by SMS" and "Audit log"
Words: "Data the LGU can act on."

**Scene 9 · 0:55–1:00 · Closing**
A shield icon with "Protected under the Data Privacy Act" (one line, small). Then both logos, "JoBauko" and "jobauko.onrender.com". Hold the final frame for at least the last 2 seconds.

## Accuracy rules: do not break these

Show only what is listed in this prompt. In particular, never show or say:
- AI, artificial intelligence, machine learning or ChatGPT
- a mobile app or app-store badges
- user counts, statistics, percentages of success or testimonials (the "82% match" in scene 5 is an example score, nothing more)
- pricing, "free" or "paid"
- social media logins
- any organization or person that is not in this prompt or on the live site

Spell the names exactly: "JoBauko" (capital J and B, one word), "Bauko", "Mountain Province", "Montanosa Tech", "barangay".

## When done

1. Watch the render frame by frame at every scene boundary (0:05, 0:11, 0:18, 0:26, 0:34, 0:41, 0:48, 0:55) and confirm each scene starts on time.
2. Check every word on screen against this prompt for spelling and against the accuracy rules.
3. Check that the logos are not stretched, cropped or recolored.
4. Then tell me where the MP4 is and list anything you could not do exactly as asked.
````

---

## 2. Script to say while the video plays

About 150 words, which takes about 60 seconds at a calm pitching pace. Each line starts when its scene appears. If you fall behind, drop the sentence in *[brackets]*.

| Time | On screen | Say |
|---|---|---|
| 0:00 | Logos, JoBauko | "Good day. This is **JoBauko**, the opportunity portal of the Municipality of Bauko." |
| 0:05 | Scattered cards | "Today, jobs, scholarships, livelihood programs and trainings are posted in different places, and many residents across our twenty-two barangays never hear about them." |
| 0:11 | One portal | "JoBauko brings all of them into one website that anyone can search." |
| 0:18 | Profile and resume | "Residents sign up with their barangay, then upload a resume, which the site reads for them to confirm. *[Or they build a clean resume right on the site.]*" |
| 0:26 | Match score | "JoBauko then recommends opportunities that fit, and always shows the reasons why." |
| 0:34 | Status tracker | "Applying takes a few clicks, and applicants can follow every step, with updates by SMS, email or in the app." |
| 0:41 | Organizations | "Every organization is verified before it can post, and hiring teams review applicants in one place." |
| 0:48 | LGU dashboard | "For the municipality, JoBauko gives reports by barangay, ready to export, so programs can reach the people who need them." |
| 0:55 | Closing | "Secure, privacy-compliant, and made for Bauko. Thank you." |

### Before you present

- Open https://jobauko.onrender.com a few minutes before you start, so the free server is awake if someone tries it.
- Play the video full screen with sound off. It has no audio.
- Practise once with a timer. If a line runs long, shorten it rather than speaking faster.
