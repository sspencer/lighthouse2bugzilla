#!/usr/bin/env ruby

require 'net/http'
require 'uri'

# you need a json lib installed or ruby 1.9
require 'json'

# MODIFY THESE PARAMS
$token = <YOUR LIGHTHOUSE_KEY_HERE>
$project = "browserplus"
$project_id = "43039-platform"

# first get an array of ticket numbers
ticketNumbers = []

# XXX : there's gotta be a better way to get ticket numbers, note, if you got more than 4 pages,
# hack or make this script better
[1,2,3,4].each { |page|
  puts "processing page #{page}"
  $ticket_url = "http://#{$project}.lighthouseapp.com/projects/#{$project_id}/tickets.json?_token=#{$token}&page=#{page}"
  x = Net::HTTP.get URI.parse($ticket_url)
  x = JSON.parse(x)
  x = x['tickets']
  x.each { |t|
    t = t['ticket']
    ticketNumbers.push(t['number'])
  }
  # be nice to lighthouse
  sleep 1
}

puts "found #{ticketNumbers.length} tickets"

tickets = [ ]

ticketNumbers.each { |ticknum|
  puts "processing ticket: #{ticknum}"
  $ticket_url = "http://#{$project}.lighthouseapp.com/projects/#{$project_id}/tickets/#{ticknum}.json?_token=#{$token}"
  x = Net::HTTP.get URI.parse($ticket_url)
  x = JSON.parse(x)['ticket']
  
  newtick = {}

  [
   'number',
   'milestone_title',
   'created_at',
   'body',
   'title',
   'attachments_count',
   'priority',
   'user_name',
   'assigned_user_name',
   'creator_name',
   'latest_body',
   'state'
  ]. each { |wantedKey|
    newtick[wantedKey] = x[wantedKey] if x.has_key? wantedKey
  }

  # comments - we'll go through versions and find versions with non-empty bodies to get
  #            comments
  newtick['comments'] = Array.new
  if x.has_key? 'versions'
    firstVersion = true
    x['versions'].each { |v|
      if firstVersion
        firstVersion = false
        next
      end
      # now lets skip 'versions' without comment bodies, and also versions with start and end with a bracket
      # (skips cruft like "bulk edit" messages)
      if !v.has_key? 'body' or v['body'] == nil or v['body'].length == 0 or
          v['body'] =~ /^\[.*\]$/
        next
      end
      comment = Hash.new
      [ 'body', 'updated_at', 'user_name' ].each { |wantedKey|
        comment[wantedKey] = v[wantedKey] if v.has_key? wantedKey
      }
      newtick['comments'].push comment
    }
  end

  # attachments - that's easy, we'll rip through top level attachments array to find attachments
  newtick['attachments'] = Array.new
  if x.has_key? 'attachments'
    x['attachments'].each { |a|
      a.each {|k,v|
        newtick['attachments'].push v['url'] if v.has_key? 'url'
      }
    }
  end

  tickets.push newtick
  # be nice to lighthouse
  sleep 1
}

File.open( "tickets.json", "w+" ) { |f| f.write(JSON.pretty_generate(tickets)) }
