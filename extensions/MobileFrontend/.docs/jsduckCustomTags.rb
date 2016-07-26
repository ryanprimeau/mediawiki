# Custom tags for JSDuck 5.x
# Originally implemented of VisualEditor-Team:
# https://github.com/wikimedia/mediawiki-extensions-VisualEditor/blob/master/.docs/CustomTags.rb
# See also:
# - https://github.com/senchalabs/jsduck/wiki/Tags
# - https://github.com/senchalabs/jsduck/wiki/Custom-tags
# - https://github.com/senchalabs/jsduck/wiki/Custom-tags/7f5c32e568eab9edc8e3365e935bcb836cb11f1d

require 'jsduck/tag/tag'

class CommonTag < JsDuck::Tag::Tag
  def initialize
    @html_position = POS_DOC + 0.1
    @repeatable = true
  end

  def parse_doc(scanner, position)
    if @multiline
      return { :tagname => @tagname, :doc => :multiline }
    else
      text = scanner.match(/.*$/)
      return { :tagname => @tagname, :doc => text }
    end
  end

  def process_doc(context, tags, position)
    context[@tagname] = tags
  end

  def format(context, formatter)
    context[@tagname].each do |tag|
      tag[:doc] = formatter.format(tag[:doc])
    end
  end
end

class SeeTag < CommonTag
  def initialize
    @tagname = :see
    @pattern = "see"
    super
  end

  def format(context, formatter)
    position = context[:files][0]
    context[@tagname].each do |tag|
      tag[:doc] = '<li>' + render_long_see(tag[:doc], formatter, position) + '</li>'
    end
  end

  def to_html(context)
    <<-EOHTML
      <h3 class="pa">Related</h3>
      <ul>
      #{ context[@tagname].map { |tag| tag[:doc] }.join("\n") }
      </ul>
    EOHTML
  end

  def render_long_see(tag, formatter, position)
    if tag =~ /\A([^\s]+)( .*)?\Z/m
      name = $1
      doc = $2 ? ': ' + $2 : ''
      return formatter.format("{@link #{name}} #{doc}")
    else
      JsDuck::Logger.warn(nil, 'Unexpected @see argument: "'+tag+'"', position)
      return tag
    end
  end
end
