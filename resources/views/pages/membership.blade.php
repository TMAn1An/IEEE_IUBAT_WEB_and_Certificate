<x-layouts.app
    :page-title="$pageTitle"
    :page-desc="$pageDesc"
    :page-url="$pageUrl"
    :current="$current"
>

<x-site.crumb :trail="[['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'], ['label' => 'Membership']]" />

<main id="main">
  <x-site.page-head eyebrow="About" title="Membership" lead="IEEE student membership connects you to a global technical community. The branch is how you use it locally." />

  <section class="section">
    <div class="wrap">
      <div class="grid grid--2" style="gap:52px;align-items:start">
        <div class="rv">
          <span class="eyebrow">Why join</span>
          <h2>Two memberships, one decision</h2>
          <p class="lead">
            You join IEEE itself, and that membership makes you part of the IEEE IUBAT Student
            Branch automatically if you study at IUBAT.
          </p>
          <p>
            IEEE membership gives you the literature, the credentials and the professional
            network. The branch gives you people to build with, events to present at, and a
            reason to use any of it before you graduate.
          </p>
          <p>
            Members also pay less at branch events. Registration for the 2026 Humanitarian
            Project Exhibition, for example, is BDT {{ $event['fees'][0]['early'] }} for IEEE student members against
            BDT {{ $event['fees'][1]['early'] }} for non-members at the early-bird rate.
          </p>
          <a class="btn btn--primary" href="https://www.ieee.org/join" target="_blank" rel="noopener">Join IEEE @icon('arrow')</a>
        </div>

        <div class="rv">
          <div class="card">
            <h3>What membership includes</h3>
            <ul class="ticks" style="margin-bottom:0">
              <li>IEEE <em>Spectrum</em> magazine subscription</li>
              <li>Discounted access to the IEEE <em>Xplore</em> Digital Library</li>
              <li>IEEE Collabratec, the members' collaboration platform</li>
              <li>The IEEE Learning Network and IEEE eLearning</li>
              <li>The IEEE Mentoring Program</li>
              <li>IEEE Job Site and career resources</li>
              <li>Reduced registration at IEEE conferences and branch events</li>
              <li>Eligibility for IEEE awards, grants and volunteer positions</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <x-site.section-head eyebrow="How to join" title="Three steps" />

      <div class="grid grid--3">
        <x-site.icon-card icon="user" title="1. Create an IEEE account" body="Register at ieee.org with your student details. Select <strong>Student Member</strong> as your grade and name IUBAT as your institution." />
        <x-site.icon-card icon="ticket" title="2. Pay the annual dues" body="IEEE sets student dues centrally and they vary by region and by the month you join. The exact figure is shown at checkout." />
        <x-site.icon-card icon="users" title="3. Tell the branch" body="Send us your IEEE membership number so we can add you to the branch roster and the event mailing list." card="card--green" />
      </div>

      <x-site.callout icon="info" title="Check the current dues before you pay">
        <p style="margin-bottom:0">IEEE reviews membership pricing every year and offers a reduced half-year rate after 1 March. Always confirm the current amount on <a href="https://www.ieee.org/join" target="_blank" rel="noopener">the IEEE join page</a> rather than relying on figures quoted elsewhere.</p>
      </x-site.callout>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="Pricing" title="What it costs" lead="IEEE student membership is priced centrally and varies by region and the month you join." />

      <div class="grid grid--2" style="gap:40px;align-items:start">
        <div class="rv">
          <div class="card" style="border-top:4px solid var(--ieee-blue)">
            <h3>Student membership (Bangladesh)</h3>
            <p style="font-size:1.4rem;font-weight:700;margin-bottom:4px">Annual student dues vary by region and by the month you join</p>
            <p style="font-size:.88rem;color:var(--muted);margin-bottom:0">IEEE sets student dues centrally and reviews the amount every year. A reduced half-year rate is often available after 1 March. Always confirm the current figure on <a href="https://www.ieee.org/join" target="_blank" rel="noopener">ieee.org/join</a> before paying.</p>
          </div>
        </div>
        <div class="rv">
          <div class="card">
            <h3>What you get for that price</h3>
            <ul class="ticks" style="margin-bottom:0">
              <li><em>IEEE Spectrum</em> magazine (digital + print)</li>
              <li>Discounted IEEE <em>Xplore</em> access</li>
              <li>IEEE Collabratec collaboration platform</li>
              <li>IEEE Learning Network courses</li>
              <li>Mentoring program and career resources</li>
              <li>Reduced registration at IEEE conferences</li>
              <li>Eligibility for IEEE awards and grants</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <x-site.section-head eyebrow="FAQ" title="Common questions" />

      <div class="grid grid--2" style="gap:24px">
        <x-site.icon-card card="card--sm" title="Do I have to be an EEE student?" body="No. IEEE membership is open to students in any engineering, computer science or related discipline at IUBAT." />
        <x-site.icon-card card="card--sm" title="Can I attend events without joining?" body="Most branch events are open to all IUBAT students. IEEE members get reduced or free registration at many events." />
        <x-site.icon-card card="card--sm" title="What if I cannot afford the dues?" body="Contact the branch. IEEE occasionally offers fee waivers and the branch can help you explore options." />
        <x-site.icon-card card="card--sm" title="How do I pay?" body="IEEE accepts credit and debit cards at checkout on ieee.org. Some sections also support bank transfer." />
      </div>
    </div>
  </section>

  <x-site.cta-block
      title="Questions before you sign up?"
      sub="Ask the committee. We would rather answer a question now than sort out a membership problem later."
      :buttons="[
          ['Contact the branch', '/contact', 'light'],
          ['Join IEEE', 'https://www.ieee.org/join', 'outline-light', true],
      ]"
  />
</main>

</x-layouts.app>
