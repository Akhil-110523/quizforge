<?xml version="1.0" encoding="UTF-8"?>
<!-- One stylesheet, three views: quiz, result, leaderboard (chosen by the root element) -->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" encoding="UTF-8"/>

  <!-- ===== VIEW 1: the quiz questions ===== -->
  <xsl:template match="/quiz">
    <xsl:for-each select="q">
      <section class="q" data-id="{@id}">
        <h2><xsl:value-of select="text"/></h2>
        <div class="opts">
          <xsl:for-each select="option">
            <label class="opt">
              <input type="radio" name="q{../@id}" value="{@i}"/>
              <kbd><xsl:value-of select="position()"/></kbd>
              <span><xsl:value-of select="."/></span>
            </label>
          </xsl:for-each>
        </div>
      </section>
    </xsl:for-each>
  </xsl:template>

  <!-- ===== VIEW 2: the result and answer review ===== -->
  <xsl:template match="/result">
    <div class="score">
      <p class="kicker">Well played, <xsl:value-of select="@name"/></p>
      <div class="big"><xsl:value-of select="@score"/><small> / <xsl:value-of select="@total"/></small></div>
      <div class="meter"><i style="width:{@percent}%"></i></div>
      <p class="verdict">
        <xsl:choose>
          <xsl:when test="@percent &gt;= 80">Outstanding</xsl:when>
          <xsl:when test="@percent &gt;= 60">Solid work</xsl:when>
          <xsl:when test="@percent &gt;= 40">Getting there</xsl:when>
          <xsl:otherwise>Keep practising</xsl:otherwise>
        </xsl:choose>
        &#183; <xsl:value-of select="@percent"/>%
      </p>
    </div>
    <xsl:for-each select="item">
      <article class="rev {@correct}">
        <h3><xsl:value-of select="position()"/>. <xsl:value-of select="text"/></h3>
        <xsl:choose>
          <xsl:when test="@correct = 'yes'">
            <p class="ans">&#10003; <xsl:value-of select="right"/></p>
          </xsl:when>
          <xsl:otherwise>
            <p class="ans bad">&#10007;
              <xsl:choose>
                <xsl:when test="string(chosen) != ''"><xsl:value-of select="chosen"/></xsl:when>
                <xsl:otherwise>No answer</xsl:otherwise>
              </xsl:choose>
            </p>
            <p class="ans">&#10003; <xsl:value-of select="right"/></p>
          </xsl:otherwise>
        </xsl:choose>
        <p class="exp"><xsl:value-of select="explain"/></p>
      </article>
    </xsl:for-each>
  </xsl:template>

  <!-- ===== VIEW 3: leaderboard (top 10 by percentage, newest first on ties) ===== -->
  <xsl:template match="/scores">
    <xsl:choose>
      <xsl:when test="not(entry)"><p class="empty">No scores yet. Be the first.</p></xsl:when>
      <xsl:otherwise>
        <ol class="board">
          <xsl:for-each select="entry">
            <xsl:sort select="@score div @total" data-type="number" order="descending"/>
            <xsl:sort select="@date" order="descending"/>
            <xsl:if test="position() &lt;= 10">
              <li>
                <span class="who"><xsl:value-of select="@name"/></span>
                <span class="cat"><xsl:value-of select="@category"/></span>
                <span class="pts"><xsl:value-of select="@score"/>/<xsl:value-of select="@total"/></span>
              </li>
            </xsl:if>
          </xsl:for-each>
        </ol>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>
</xsl:stylesheet>
