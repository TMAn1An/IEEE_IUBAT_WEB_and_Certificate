<x-layouts.app
    :page-title="$pageTitle"
    :page-desc="$pageDesc"
    :page-url="$pageUrl"
    :page-image="$pageImage"
    :page-image-w="$pageImageW"
    :page-image-h="$pageImageH"
    :current="$current"
>

<x-site.crumb :trail="[['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'], ['label' => 'Committees']]" />

<main id="main">
  <x-site.page-head eyebrow="About" title="Executive Committee" lead="The volunteers, advisors and faculty who plan, fund and run the branch's activities, and who represent IEEE IUBAT to the Bangladesh Section." />

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="Faculty" title="Advisory Committee" lead="Faculty members who guide the branch's direction and act as liaisons between the student body and the university." />

      <div class="grid grid--3">
        <x-site.person-card :person="$siteContent->personByKey('counsellor')" />
        <x-site.person-card :person="$siteContent->personByKey('adviser-2')" />
        <x-site.person-card :person="$siteContent->personByKey('adviser-3')" />
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <x-site.section-head eyebrow="Guidance" title="Student Advisory Panel" lead="Senior student members who mentor the executive committee and help maintain continuity across terms." />

      <div class="grid grid--3">
        <x-site.person-card :person="$siteContent->personByKey('advisor-1')" />
        <x-site.person-card :person="$siteContent->personByKey('mentor-1')" />
      </div>
    </div>
  </section>

  <section class="section">
    <div class="wrap">
      <x-site.section-head eyebrow="2026&ndash;27 term" title="Who runs the branch" lead="The Executive Committee is responsible for the branch's direction, its events and budgets, its reporting to IEEE, and the day-to-day business of keeping members active." />

      <div class="grid grid--3">
        @foreach ($execCommittee as $person)
          <x-site.person-card :person="$person" :tel="false" />
        @endforeach
      </div>
    </div>
  </section>

  <section class="section section--shell">
    <div class="wrap">
      <div class="split">
        <div class="rv">
          <span class="eyebrow">Responsibilities</span>
          <h2>What the committee does</h2>
          <ul class="ticks">
            <li>Sets the branch's direction and annual plan</li>
            <li>Organises events, workshops and competitions</li>
            <li>Handles budgets, sponsorship and expenditure</li>
            <li>Reports to the IEEE Bangladesh Section and Region 10</li>
            <li>Recruits members and runs volunteer development</li>
            <li>Represents IEEE IUBAT to the university and to partners</li>
          </ul>
        </div>

        <div class="rv">
          <x-site.callout icon="info" title="Want to volunteer?">
            <p>Committee roles open at the start of each term, but volunteering does not wait for an election &mdash; most events need help long before that. Write to the Chair and say what you would like to work on.</p>
            <p style="margin-bottom:0"><a href="/contact">Get in touch with the team</a></p>
          </x-site.callout>
        </div>
      </div>
    </div>
  </section>


  <x-site.cta-block
      title="Become part of the branch"
      sub="IEEE membership opens the door to the world's largest technical professional organization &mdash; and the branch is where you put it to use."
      :buttons="[
          ['How to join', '/membership', 'light'],
          ['Talk to us first', '/contact', 'outline-light'],
      ]"
  />
</main>

</x-layouts.app>
