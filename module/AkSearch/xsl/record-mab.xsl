<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:output method="html" indent="yes" />

	<xsl:template match="/">
		<xsl:apply-templates />
	</xsl:template>

	<xsl:template match="record">
		<table class="citation table table-striped">
			<tr class="pace-car">
				<th width="15%" />
				<td width="5%" />
				<td width="5%" />
				<td width="*" />
			</tr>
			<tr>
				<th>LEADER</th>
				<td colspan="3"><xsl:value-of select="//leader" /></td>
			</tr>
			<xsl:apply-templates select="datafield|controlfield" />
		</table>
	</xsl:template>

	<xsl:template match="//controlfield">
		<tr>
			<th><xsl:value-of select="@tag" /></th>
			<td colspan="3"><xsl:value-of select="." /></td>
		</tr>
	</xsl:template>

	<xsl:template match="//datafield">
		<xsl:variable name="indicator1" select="@ind1" />
		<xsl:variable name="indicator2" select="@ind2" />
		<tr>
			<th><xsl:value-of select="@tag" /></th>

			<!-- ORIGINAL: <td><xsl:value-of select="@ind1"/></td> -->
			<!-- Replace all "*" and "-" with "_" -->
			<xsl:choose>
				<xsl:when test="$indicator1 = '*'">
					<td>_</td>
				</xsl:when>
				<xsl:when test="$indicator1 = '-'">
					<td>_</td>
				</xsl:when>
				<xsl:when test="$indicator1 = ' '">
					<td>_</td>
				</xsl:when>
				<xsl:otherwise>
					<td><xsl:value-of select="$indicator1" /></td>
				</xsl:otherwise>
			</xsl:choose>

			<!-- ORIGINAL: <td><xsl:value-of select="@ind2"/></td> -->
			<!-- Replace all "*" and "-" with "_" -->
			<xsl:choose>
				<xsl:when test="$indicator2 = '*'">
					<td>_</td>
				</xsl:when>
				<xsl:when test="$indicator2 = '-'">
					<td>_</td>
				</xsl:when>
				<xsl:when test="$indicator2 = ' '">
					<td>_</td>
				</xsl:when>
				<xsl:otherwise>
					<td><xsl:value-of select="$indicator2" /></td>
				</xsl:otherwise>
			</xsl:choose>

			<td><xsl:apply-templates select="subfield" /></td>
		</tr>
	</xsl:template>

	<xsl:template match="subfield">
		<strong>|<xsl:value-of select="@code" /></strong>&#160;<xsl:value-of select="." />&#160;
	</xsl:template>

</xsl:stylesheet>